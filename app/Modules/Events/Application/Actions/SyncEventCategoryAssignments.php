<?php

namespace App\Modules\Events\Application\Actions;

use App\Exceptions\FoundationException;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Domain\CategoryLockStatus;
use App\Modules\Events\Infrastructure\Persistence\Models\CategoryTemplate;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class SyncEventCategoryAssignments
{
    /**
     * @param  list<array{
     *   category_template_id:int|string,
     *   is_paid?:bool,
     *   price_minor?:int|string|null,
     *   currency?:string|null,
     *   venues?:list<array{
     *     event_venue_id:int|string,
     *     days?:list<array{date:string,capacity?:int|string|null}>
     *   }>
     * }>  $categories
     * @return list<EventCategory>
     */
    public function execute(Event $event, array $categories): array
    {
        if (CategoryLockStatus::locksCategories((string) $event->status)) {
            throw FoundationException::conflict(
                'event_categories_locked',
                'Categories cannot be changed while the event is published or live.',
            );
        }

        $allowedDates = $this->eventDates($event);
        $venueIds = EventVenue::query()
            ->where('event_id', $event->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return DB::transaction(function () use ($event, $categories, $allowedDates, $venueIds): array {
            $keepIds = [];
            $results = [];

            foreach ($categories as $index => $row) {
                $templateId = (int) $row['category_template_id'];
                $template = CategoryTemplate::query()
                    ->where('tenant_id', $event->tenant_id)
                    ->with('privileges.privilege')
                    ->findOrFail($templateId);

                $isPaid = (bool) ($row['is_paid'] ?? false);
                $priceMinor = $isPaid ? max(0, (int) ($row['price_minor'] ?? 0)) : 0;
                $currency = strtoupper((string) ($row['currency'] ?? 'SAR'));
                if (strlen($currency) !== 3) {
                    $currency = 'SAR';
                }

                $category = EventCategory::query()->updateOrCreate(
                    [
                        'event_id' => $event->id,
                        'category_template_id' => $template->id,
                    ],
                    [
                        'name' => $template->name,
                        'name_ar' => $template->name_ar,
                        'slug' => $template->slug,
                        'color' => $template->color,
                        'is_paid' => $isPaid,
                        'price_minor' => $priceMinor,
                        'currency' => $currency,
                        'capacity' => null,
                        'sort_order' => $index,
                    ],
                );

                $category->privileges()->delete();
                foreach ($template->privileges as $link) {
                    $privilege = $link->privilege;
                    if ($privilege === null) {
                        continue;
                    }

                    $category->privileges()->create([
                        'key' => $privilege->key,
                        'label' => $privilege->label,
                        'label_ar' => $privilege->label_ar,
                        'effect' => $link->effect ?: $privilege->effect,
                        'target_type' => $privilege->target_type,
                        'target_id' => $privilege->target_id,
                    ]);
                }

                $category->venues()->delete();

                foreach ($row['venues'] ?? [] as $venueIndex => $venueRow) {
                    $eventVenueId = (int) $venueRow['event_venue_id'];
                    if (! in_array($eventVenueId, $venueIds, true)) {
                        throw FoundationException::validation(
                            'invalid_event_venue',
                            'One or more venues do not belong to this event.',
                        );
                    }

                    $categoryVenue = $category->venues()->create([
                        'event_venue_id' => $eventVenueId,
                        'sort_order' => $venueIndex,
                    ]);

                    $days = $venueRow['days'] ?? [];
                    if ($days === []) {
                        throw FoundationException::validation(
                            'category_days_required',
                            'Each selected venue must include at least one date.',
                        );
                    }

                    foreach ($days as $day) {
                        $date = CarbonImmutable::parse((string) $day['date'])->toDateString();
                        if (! in_array($date, $allowedDates, true)) {
                            throw FoundationException::validation(
                                'category_date_out_of_range',
                                'Category dates must fall within the event schedule.',
                            );
                        }

                        $rawCapacity = $day['capacity'] ?? null;
                        $capacity = ($rawCapacity === null || $rawCapacity === '')
                            ? null
                            : (int) $rawCapacity;

                        if ($capacity !== null && $capacity < 1) {
                            throw FoundationException::validation(
                                'category_capacity_invalid',
                                'Capacity must be empty (unlimited) or at least 1.',
                            );
                        }

                        $categoryVenue->days()->create([
                            'date' => $date,
                            'capacity' => $capacity,
                        ]);
                    }
                }

                if (($row['venues'] ?? []) === []) {
                    throw FoundationException::validation(
                        'category_venues_required',
                        'Each enabled category must include at least one venue.',
                    );
                }

                $keepIds[] = $category->id;
                $results[] = $category->load(['privileges', 'venues.days', 'venues.venue']);
            }

            EventCategory::query()
                ->where('event_id', $event->id)
                ->when($keepIds !== [], fn ($query) => $query->whereNotIn('id', $keepIds))
                ->when($keepIds === [], fn ($query) => $query)
                ->delete();

            return $results;
        });
    }

    /** @return list<string> */
    private function eventDates(Event $event): array
    {
        if ($event->start_at === null || $event->end_at === null) {
            return [];
        }

        $start = $event->start_at->timezone($event->timezone)->startOfDay();
        $end = $event->end_at->timezone($event->timezone)->startOfDay();
        $dates = [];

        for ($cursor = $start; $cursor->lessThanOrEqualTo($end); $cursor = $cursor->addDay()) {
            $dates[] = $cursor->toDateString();
        }

        return $dates;
    }
}
