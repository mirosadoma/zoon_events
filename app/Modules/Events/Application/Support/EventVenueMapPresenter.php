<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\Events\Infrastructure\Persistence\Models\EventVenueMap;

final class EventVenueMapPresenter
{
    /**
     * @return array{
     *   id: string,
     *   venue_id: string,
     *   image_url: ?string,
     *   image_path: string,
     *   width: ?int,
     *   height: ?int
     * }
     */
    public static function toArray(EventVenueMap $map): array
    {
        return [
            'id' => (string) $map->id,
            'venue_id' => (string) $map->venue_id,
            'image_url' => $map->imageUrl(),
            'image_path' => (string) $map->image_path,
            'width' => $map->width !== null ? (int) $map->width : null,
            'height' => $map->height !== null ? (int) $map->height : null,
        ];
    }
}
