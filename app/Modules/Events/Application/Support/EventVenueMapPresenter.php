<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\Events\Infrastructure\Persistence\Models\EventVenueMap;

final class EventVenueMapPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(EventVenueMap $map): array
    {
        $opacity = $map->overlay_opacity;
        $resolvedOpacity = is_numeric($opacity) ? (float) $opacity : 0.85;

        return [
            'id' => (string) $map->id,
            'venue_id' => (string) $map->venue_id,
            'image_url' => $map->image_path !== '' && $map->image_path !== null ? $map->imageUrl() : null,
            'image_path' => (string) ($map->image_path ?? ''),
            'width' => $map->width !== null ? (int) $map->width : null,
            'height' => $map->height !== null ? (int) $map->height : null,
            'overlay_opacity' => max(0.0, min(1.0, $resolvedOpacity)),
            'remove_background' => (bool) ($map->remove_background ?? false),
            'show_base_map' => (bool) ($map->show_base_map ?? true),
            'map_center_lat' => $map->map_center_lat !== null ? (float) $map->map_center_lat : null,
            'map_center_lng' => $map->map_center_lng !== null ? (float) $map->map_center_lng : null,
            'map_zoom' => $map->map_zoom !== null ? (float) $map->map_zoom : null,
            'map_heading' => $map->map_heading !== null ? (float) $map->map_heading : 0.0,
            'map_type' => $map->map_type !== null && $map->map_type !== '' ? (string) $map->map_type : 'hybrid',
            'overlay_north' => $map->overlay_north !== null ? (float) $map->overlay_north : null,
            'overlay_south' => $map->overlay_south !== null ? (float) $map->overlay_south : null,
            'overlay_east' => $map->overlay_east !== null ? (float) $map->overlay_east : null,
            'overlay_west' => $map->overlay_west !== null ? (float) $map->overlay_west : null,
            'overlay_rotation' => $map->overlay_rotation !== null ? (float) $map->overlay_rotation : 0.0,
        ];
    }
}
