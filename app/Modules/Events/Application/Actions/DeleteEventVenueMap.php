<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventVenueMap;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class DeleteEventVenueMap
{
    /**
     * Remove the floor-plan image file and clear image fields,
     * while keeping camera / overlay settings for the geo editor.
     */
    public function execute(string $tenantId, Event $event, int $venueId): ?EventVenueMap
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
            return null;
        }

        if ($map->image_path !== null && $map->image_path !== '') {
            Storage::disk('public')->delete($map->image_path);
        }

        $map->fill([
            'image_path' => '',
            'width' => null,
            'height' => null,
            'remove_background' => false,
        ])->save();

        return $map->refresh();
    }
}
