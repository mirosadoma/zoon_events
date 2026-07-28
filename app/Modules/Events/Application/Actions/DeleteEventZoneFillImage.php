<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class DeleteEventZoneFillImage
{
    public function execute(
        string $tenantId,
        Event $event,
        int $venueId,
        int $zoneId,
    ): EventZone {
        $zone = EventZone::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->where('venue_id', $venueId)
            ->whereKey($zoneId)
            ->first();

        if (! $zone instanceof EventZone) {
            throw new InvalidArgumentException('Zone must belong to this event venue.');
        }

        if ($zone->fill_image_path !== null && $zone->fill_image_path !== '') {
            Storage::disk('public')->delete($zone->fill_image_path);
        }

        $zone->fill([
            'fill_image_path' => null,
        ])->save();

        return $zone->refresh();
    }
}
