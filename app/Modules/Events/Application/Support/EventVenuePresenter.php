<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;

final readonly class EventVenuePresenter
{
    /** @return list<array<string, mixed>> */
    public function forEvent(Event $event): array
    {
        return EventVenue::query()
            ->with([
                'city:id,country_id,name_en,name_ar',
                'country:id,name_en,name_ar',
            ])
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (EventVenue $venue): array => [
                'id' => (string) $venue->id,
                'name' => ['en' => $venue->name_en, 'ar' => $venue->name_ar],
                'city' => [
                    'en' => $venue->city?->name_en ?? '',
                    'ar' => $venue->city?->name_ar ?? '',
                ],
                'country' => [
                    'en' => $venue->country?->name_en ?? '',
                    'ar' => $venue->country?->name_ar ?? '',
                ],
                'location_address' => $venue->location_address ?? '',
                'start_at' => EventWallClockDateTime::toIso8601($venue->start_at, $event->timezone),
                'end_at' => EventWallClockDateTime::toIso8601($venue->end_at, $event->timezone),
            ])
            ->values()
            ->all();
    }
}
