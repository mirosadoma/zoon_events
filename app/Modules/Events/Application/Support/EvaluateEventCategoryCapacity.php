<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategory;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class EvaluateEventCategoryCapacity
{
    /**
     * @return list<array{
     *   id: string,
     *   capacity: int|null,
     *   taken: int,
     *   remaining: int|null,
     *   is_full: bool
     * }>
     */
    public function forEvent(Event $event): array
    {
        $categories = EventCategory::query()
            ->where('event_id', $event->id)
            ->with(['venues.days'])
            ->orderBy('sort_order')
            ->get();

        if ($categories->isEmpty()) {
            return [];
        }

        $takenByCategory = $this->takenCounts($event, $categories->pluck('id')->all());

        return $categories
            ->map(function (EventCategory $category) use ($takenByCategory): array {
                $capacity = $this->resolveCapacity($category);
                $taken = (int) ($takenByCategory[(string) $category->id] ?? 0);
                $remaining = $capacity === null ? null : max(0, $capacity - $taken);

                return [
                    'id' => (string) $category->id,
                    'capacity' => $capacity,
                    'taken' => $taken,
                    'remaining' => $remaining,
                    'is_full' => $capacity !== null && $remaining === 0,
                ];
            })
            ->values()
            ->all();
    }

    public function isEventFullyBooked(Event $event): bool
    {
        $snapshots = $this->forEvent($event);
        if ($snapshots === []) {
            return false;
        }

        // Only public category registration can be fully booked when every category is limited and full.
        foreach ($snapshots as $snapshot) {
            if (! $snapshot['is_full']) {
                return false;
            }
        }

        return true;
    }

    public function assertCategoryAvailable(Event $event, EventCategory $category): void
    {
        $snapshot = collect($this->forEvent($event))
            ->firstWhere('id', (string) $category->id);

        if ($snapshot !== null && ($snapshot['is_full'] ?? false) === true) {
            throw ValidationException::withMessages([
                'event_category_id' => ['This category is full.'],
            ]);
        }
    }

    /**
     * @param  list<int|string>  $categoryIds
     * @return array<string, int>
     */
    private function takenCounts(Event $event, array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        /** @var Collection<int, object{event_category_id: mixed, aggregate: mixed}> $rows */
        $rows = Order::query()
            ->where('event_id', $event->id)
            ->whereIn('event_category_id', $categoryIds)
            ->whereIn('status', [
                OrderStatus::Paid->value,
                OrderStatus::PendingPayment->value,
            ])
            ->selectRaw('event_category_id, COUNT(*) as aggregate')
            ->groupBy('event_category_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->event_category_id] = (int) $row->aggregate;
        }

        return $map;
    }

    private function resolveCapacity(EventCategory $category): ?int
    {
        $days = $category->venues
            ->flatMap(fn ($venue) => $venue->days)
            ->values();

        if ($days->isNotEmpty()) {
            $byDate = [];
            foreach ($days as $day) {
                $date = $day->date instanceof \Carbon\CarbonInterface
                    ? $day->date->toDateString()
                    : (string) $day->date;
                if ($day->capacity === null) {
                    // Any unlimited day keeps the category open.
                    return null;
                }

                $byDate[$date] = ($byDate[$date] ?? 0) + (int) $day->capacity;
            }

            if ($byDate !== []) {
                return max($byDate);
            }
        }

        if ($category->capacity !== null && (int) $category->capacity > 0) {
            return (int) $category->capacity;
        }

        return null;
    }
}
