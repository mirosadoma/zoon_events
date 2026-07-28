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
    /**
     * @param  array{
     *   overlay_north?: float|null,
     *   overlay_south?: float|null,
     *   overlay_east?: float|null,
     *   overlay_west?: float|null,
     *   map_center_lat?: float|null,
     *   map_center_lng?: float|null,
     *   map_zoom?: float|null,
     *   map_heading?: float|null,
     *   map_type?: string|null
     * }  $camera
     */
    public function execute(
        string $tenantId,
        Event $event,
        int $venueId,
        UploadedFile $image,
        ?int $width = null,
        ?int $height = null,
        array $camera = [],
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

        $cameraPayload = array_filter(
            [
                'overlay_north' => $camera['overlay_north'] ?? null,
                'overlay_south' => $camera['overlay_south'] ?? null,
                'overlay_east' => $camera['overlay_east'] ?? null,
                'overlay_west' => $camera['overlay_west'] ?? null,
                'map_center_lat' => $camera['map_center_lat'] ?? null,
                'map_center_lng' => $camera['map_center_lng'] ?? null,
                'map_zoom' => $camera['map_zoom'] ?? null,
                'map_heading' => $camera['map_heading'] ?? null,
                'map_type' => $camera['map_type'] ?? null,
            ],
            static fn ($value): bool => $value !== null,
        );

        if ($existing instanceof EventVenueMap) {
            if ($existing->image_path !== '' && $existing->image_path !== $path) {
                Storage::disk('public')->delete($existing->image_path);
            }

            $existing->fill([
                'image_path' => $path,
                'width' => $width,
                'height' => $height,
                ...$cameraPayload,
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
            'overlay_opacity' => 0.85,
            'remove_background' => false,
            'show_base_map' => true,
            'map_center_lat' => $camera['map_center_lat'] ?? ($venue->latitude !== null ? (float) $venue->latitude : null),
            'map_center_lng' => $camera['map_center_lng'] ?? ($venue->longitude !== null ? (float) $venue->longitude : null),
            'map_zoom' => $camera['map_zoom'] ?? 18,
            'map_heading' => $camera['map_heading'] ?? 0,
            'map_type' => $camera['map_type'] ?? 'hybrid',
            ...$cameraPayload,
        ]);
    }
}
