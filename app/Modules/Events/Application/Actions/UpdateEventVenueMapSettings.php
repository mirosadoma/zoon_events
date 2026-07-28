<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Application\Support\RelativeToGeoCoordinates;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventVenueMap;
use InvalidArgumentException;

final class UpdateEventVenueMapSettings
{
    /**
     * @param  array{
     *   overlay_opacity?: float|null,
     *   remove_background?: bool|null,
     *   show_base_map?: bool|null,
     *   map_center_lat?: float|null,
     *   map_center_lng?: float|null,
     *   map_zoom?: float|null,
     *   map_heading?: float|null,
     *   map_type?: string|null,
     *   overlay_north?: float|null,
     *   overlay_south?: float|null,
     *   overlay_east?: float|null,
     *   overlay_west?: float|null,
     *   overlay_rotation?: float|null
     * }  $settings
     */
    public function execute(
        string $tenantId,
        Event $event,
        int $venueId,
        array $settings,
    ): EventVenueMap {
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

        $defaults = [
            'tenant_id' => $tenantId,
            'event_id' => $event->id,
            'venue_id' => $venueId,
            'image_path' => '',
            'overlay_opacity' => 0.85,
            'remove_background' => false,
            'show_base_map' => true,
            'map_center_lat' => $venue->latitude !== null ? (float) $venue->latitude : null,
            'map_center_lng' => $venue->longitude !== null ? (float) $venue->longitude : null,
            'map_zoom' => 18.0,
            'map_heading' => 0.0,
            'map_type' => 'hybrid',
        ];

        if (! $map instanceof EventVenueMap) {
            $map = EventVenueMap::query()->create($defaults);
        }

        $payload = [];
        foreach ([
            'overlay_opacity',
            'remove_background',
            'show_base_map',
            'map_center_lat',
            'map_center_lng',
            'map_zoom',
            'map_heading',
            'map_type',
            'overlay_north',
            'overlay_south',
            'overlay_east',
            'overlay_west',
            'overlay_rotation',
        ] as $key) {
            if (array_key_exists($key, $settings) && $settings[$key] !== null) {
                $payload[$key] = $settings[$key];
            }
        }

        if (array_key_exists('overlay_opacity', $payload)) {
            $payload['overlay_opacity'] = round(max(0, min(1, (float) $payload['overlay_opacity'])), 2);
        }
        if (array_key_exists('map_zoom', $payload)) {
            $payload['map_zoom'] = round(max(1, min(22, (float) $payload['map_zoom'])), 2);
        }
        if (array_key_exists('map_heading', $payload)) {
            $heading = (float) $payload['map_heading'];
            $payload['map_heading'] = round(fmod(($heading % 360) + 360, 360), 2);
        }
        if (array_key_exists('overlay_rotation', $payload)) {
            $rotation = (float) $payload['overlay_rotation'];
            $payload['overlay_rotation'] = round(fmod(($rotation % 360) + 360, 360), 3);
        }

        if ($payload !== []) {
            $map->fill($payload)->save();
        }

        $map = $map->refresh();
        UpdateEventVenueMapSettings::ensureOverlayBounds($map);
        (new ConvertRelativeMapGeometry)->execute($map->refresh());

        return $map->refresh();
    }

    /**
     * Fill overlay bounds from camera when missing (used after image upload).
     */
    public static function ensureOverlayBounds(EventVenueMap $map, ?float $aspect = null): EventVenueMap
    {
        if (
            $map->overlay_north !== null
            && $map->overlay_south !== null
            && $map->overlay_east !== null
            && $map->overlay_west !== null
        ) {
            return $map;
        }

        $lat = $map->map_center_lat !== null ? (float) $map->map_center_lat : null;
        $lng = $map->map_center_lng !== null ? (float) $map->map_center_lng : null;
        $zoom = $map->map_zoom !== null ? (float) $map->map_zoom : 18.0;

        if ($lat === null || $lng === null) {
            return $map;
        }

        $bounds = RelativeToGeoCoordinates::boundsFromCamera($lat, $lng, $zoom, $aspect ?? 1.6);
        $map->fill($bounds)->save();

        return $map->refresh();
    }
}
