<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventVenueMap;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class DeleteEventVenueMap
{
    public function execute(string $tenantId, Event $event, int $venueId): void
    {
        $venue = EventVenue::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->whereKey($venueId)
            ->first();

        if (! $venue instanceof EventVenue) {
            throw new InvalidArgumentException('Map venue must belong to this event.');
        }

        $map = EventVenueMap::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->where('venue_id', $venueId)
            ->first();

        if (! $map instanceof EventVenueMap) {
            return;
        }

        if ($map->image_path !== '') {
            Storage::disk('public')->delete($map->image_path);
        }

        $map->delete();
    }
}
