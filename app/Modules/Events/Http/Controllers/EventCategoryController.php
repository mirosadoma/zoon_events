<?php

namespace App\Modules\Events\Http\Controllers;

use App\Exceptions\FoundationException;
use App\Http\Controllers\Controller;
use App\Modules\Events\Application\Actions\SyncEventCategoryAssignments;
use App\Modules\Events\Domain\CategoryLockStatus;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategory;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategoryPrivilege;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategoryVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategoryVenueDay;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;

class EventCategoryController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contextStore,
        private readonly SyncEventCategoryAssignments $syncAssignments,
    ) {}

    public function index(string $event_id)
    {
        $event = $this->event($event_id);

        $categories = EventCategory::query()
            ->where('event_id', $event->id)
            ->with(['privileges', 'venues.days', 'venues.venue'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (EventCategory $category) => $this->mapCategory($category));

        return $this->success($categories->all());
    }

    public function sync(Request $request, string $event_id)
    {
        $event = $this->event($event_id);

        $validated = $request->validate([
            'categories' => ['present', 'array'],
            'categories.*.category_template_id' => ['required', 'integer', 'exists:category_templates,id'],
            'categories.*.is_paid' => ['sometimes', 'boolean'],
            'categories.*.price_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'categories.*.currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'categories.*.venues' => ['required', 'array', 'min:1'],
            'categories.*.venues.*.event_venue_id' => ['required', 'integer', 'exists:event_venues,id'],
            'categories.*.venues.*.days' => ['required', 'array', 'min:1'],
            'categories.*.venues.*.days.*.date' => ['required', 'date'],
            'categories.*.venues.*.days.*.capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        $categories = collect($validated['categories'])->map(function (array $category): array {
            $category['venues'] = collect($category['venues'] ?? [])->map(function (array $venue): array {
                $venue['days'] = collect($venue['days'] ?? [])->map(function (array $day): array {
                    $day['capacity'] = array_key_exists('capacity', $day) && $day['capacity'] !== null && $day['capacity'] !== ''
                        ? (int) $day['capacity']
                        : null;

                    return $day;
                })->all();

                return $venue;
            })->all();

            return $category;
        })->all();

        $synced = $this->syncAssignments->execute($event, $categories);

        return $this->success(array_map(fn (EventCategory $category) => $this->mapCategory($category), $synced));
    }

    public function destroy(string $event_id, string $category_id)
    {
        $event = $this->event($event_id);

        if (CategoryLockStatus::locksCategories((string) $event->status)) {
            throw FoundationException::conflict(
                'event_categories_locked',
                'Categories cannot be changed while the event is published or live.',
            );
        }

        $category = EventCategory::query()
            ->where('event_id', $event->id)
            ->findOrFail($category_id);

        $category->delete();

        return $this->empty();
    }

    private function event(string $eventId): Event
    {
        $context = $this->contextStore->current();

        return Event::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($eventId);
    }

    private function mapCategory(EventCategory $category): array
    {
        return [
            'id' => (string) $category->id,
            'event_id' => (string) $category->event_id,
            'category_template_id' => $category->category_template_id !== null ? (string) $category->category_template_id : null,
            'name' => $category->name,
            'name_ar' => $category->name_ar,
            'slug' => $category->slug,
            'color' => $category->color,
            'is_paid' => (bool) $category->is_paid,
            'price_minor' => (int) $category->price_minor,
            'currency' => (string) ($category->currency ?: 'SAR'),
            'sort_order' => $category->sort_order,
            'privileges' => $category->privileges->map(fn (EventCategoryPrivilege $privilege) => [
                'id' => (string) $privilege->id,
                'key' => $privilege->key,
                'label' => $privilege->label,
                'label_ar' => $privilege->label_ar,
                'effect' => $privilege->effect,
                'target_type' => $privilege->target_type,
                'target_id' => $privilege->target_id,
            ])->values()->all(),
            'venues' => $category->venues->map(fn (EventCategoryVenue $venue) => [
                'id' => (string) $venue->id,
                'event_venue_id' => (string) $venue->event_venue_id,
                'venue_name' => [
                    'en' => $venue->venue?->name_en,
                    'ar' => $venue->venue?->name_ar,
                ],
                'sort_order' => $venue->sort_order,
                'days' => $venue->days->map(fn (EventCategoryVenueDay $day) => [
                    'id' => (string) $day->id,
                    'date' => $day->date?->toDateString(),
                    'capacity' => $day->capacity,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
