<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventVenueMap;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class UpsertEventVenueMap
{
    public function execute(
        string $tenantId,
        Event $event,
        int $venueId,
        UploadedFile $image,
        ?int $width = null,
        ?int $height = null,
    ): EventVenueMap {
        $venue = EventVenue::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->whereKey($venueId)
            ->first();

        if (! $venue instanceof EventVenue) {
            throw new InvalidArgumentException('Map venue must belong to this event.');
        }

        $path = $image->store(
            "tenants/{$tenantId}/events/{$event->id}/venue-maps",
            'public',
        );

        $existing = EventVenueMap::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->where('venue_id', $venueId)
            ->first();

        if ($existing instanceof EventVenueMap) {
            if ($existing->image_path !== '' && $existing->image_path !== $path) {
                Storage::disk('public')->delete($existing->image_path);
            }

            $existing->fill([
                'image_path' => $path,
                'width' => $width,
                'height' => $height,
            ])->save();

            return $existing->refresh();
        }

        return EventVenueMap::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $event->id,
            'venue_id' => $venueId,
            'image_path' => $path,
            'width' => $width,
            'height' => $height,
        ]);
    }
}
