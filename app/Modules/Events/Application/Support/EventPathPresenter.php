<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\Events\Infrastructure\Persistence\Models\EventPath;

final class EventPathPresenter
{
    /**
     * @return array{
     *   id: string,
     *   venue_id: string,
     *   name: array{en: string, ar: string},
     *   name_en: ?string,
     *   name_ar: ?string,
     *   polyline_coordinates: list<array{x: float, y: float}>,
     *   from_zone_id: ?string,
     *   to_zone_id: ?string,
     *   stroke_color: ?string,
     *   stroke_width: ?int,
     *   opacity: ?int,
     *   sort_order: int
     * }
     */
    public static function toArray(EventPath $path): array
    {
        $nameEn = $path->name_en !== null && $path->name_en !== '' ? (string) $path->name_en : '';
        $nameAr = $path->name_ar !== null && $path->name_ar !== '' ? (string) $path->name_ar : '';

        return [
            'id' => (string) $path->id,
            'venue_id' => (string) $path->venue_id,
            'name' => ['en' => $nameEn, 'ar' => $nameAr],
            'name_en' => $nameEn !== '' ? $nameEn : null,
            'name_ar' => $nameAr !== '' ? $nameAr : null,
            'polyline_coordinates' => EventZonePresenter::normalizeCoordinates($path->polyline_coordinates) ?? [],
            'coordinate_space' => (string) ($path->coordinate_space ?? 'relative'),
            'from_zone_id' => $path->from_zone_id !== null ? (string) $path->from_zone_id : null,
            'to_zone_id' => $path->to_zone_id !== null ? (string) $path->to_zone_id : null,
            'stroke_color' => $path->stroke_color,
            'stroke_width' => $path->stroke_width !== null ? (int) $path->stroke_width : null,
            'opacity' => $path->opacity !== null ? (int) $path->opacity : null,
            'sort_order' => (int) $path->sort_order,
        ];
    }

    /**
     * @return array{
     *   id: string,
     *   name: array{en: string, ar: string},
     *   polyline_coordinates: list<array{x: float, y: float}>,
     *   from_zone_id: ?string,
     *   to_zone_id: ?string,
     *   stroke_color: ?string,
     *   stroke_width: ?int,
     *   opacity: ?int
     * }
     */
    public static function toPublicMapArray(EventPath $path): array
    {
        $base = self::toArray($path);

        return [
            'id' => $base['id'],
            'name' => $base['name'],
            'coordinate_space' => $base['coordinate_space'],
            'polyline_coordinates' => $base['polyline_coordinates'],
            'from_zone_id' => $base['from_zone_id'],
            'to_zone_id' => $base['to_zone_id'],
            'stroke_color' => $base['stroke_color'],
            'stroke_width' => $base['stroke_width'],
            'opacity' => $base['opacity'],
        ];
    }
}
