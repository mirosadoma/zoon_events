<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\Events\Domain\EventZoneShapeType;
use App\Modules\Events\Domain\EventZoneType;
use App\Modules\Events\Infrastructure\Persistence\Models\EventAgendaItem;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;

final class EventZonePresenter
{
    /**
     * @return array{
     *   id: string,
     *   venue_id: string,
     *   name: array{en: string, ar: string},
     *   zone_name_en: string,
     *   zone_name_ar: string,
     *   description_en: ?string,
     *   description_ar: ?string,
     *   type: string,
     *   capacity: ?int,
     *   shape_type: ?string,
     *   polygon_coordinates: ?list<array{x: float, y: float}>,
     *   shape_radius: ?float,
     *   label: ?string,
     *   google_maps_url: ?string,
     *   lat: ?float,
     *   lng: ?float,
     *   fill_color: ?string,
     *   stroke_color: ?string,
     *   opacity: ?int,
     *   stroke_width: ?int
     * }
     */
    public static function toArray(EventZone $zone): array
    {
        $type = $zone->type instanceof EventZoneType ? $zone->type->value : (string) $zone->type;
        $shapeType = $zone->shape_type instanceof EventZoneShapeType
            ? $zone->shape_type->value
            : ($zone->shape_type !== null ? (string) $zone->shape_type : null);
        $nameEn = (string) $zone->zone_name_en;
        $nameAr = (string) $zone->zone_name_ar;

        return [
            'id' => (string) $zone->id,
            'venue_id' => (string) $zone->venue_id,
            'name' => ['en' => $nameEn, 'ar' => $nameAr],
            'zone_name_en' => $nameEn,
            'zone_name_ar' => $nameAr,
            'description_en' => $zone->description_en !== null && $zone->description_en !== ''
                ? (string) $zone->description_en
                : null,
            'description_ar' => $zone->description_ar !== null && $zone->description_ar !== ''
                ? (string) $zone->description_ar
                : null,
            'type' => $type,
            'capacity' => $zone->capacity !== null ? (int) $zone->capacity : null,
            'shape_type' => $shapeType,
            'polygon_coordinates' => self::normalizeCoordinates($zone->polygon_coordinates),
            'shape_radius' => $zone->shape_radius !== null ? (float) $zone->shape_radius : null,
            'shape_rotation' => $zone->shape_rotation !== null ? (float) $zone->shape_rotation : 0.0,
            'shape_radius_y' => $zone->shape_radius_y !== null ? (float) $zone->shape_radius_y : null,
            'label' => $zone->label !== null && $zone->label !== '' ? (string) $zone->label : null,
            'google_maps_url' => $zone->google_maps_url !== null && $zone->google_maps_url !== ''
                ? (string) $zone->google_maps_url
                : null,
            'lat' => $zone->lat !== null ? (float) $zone->lat : null,
            'lng' => $zone->lng !== null ? (float) $zone->lng : null,
            'fill_color' => $zone->fill_color,
            'stroke_color' => $zone->stroke_color,
            'opacity' => $zone->opacity !== null ? (int) $zone->opacity : null,
            'stroke_width' => $zone->stroke_width !== null ? (int) $zone->stroke_width : null,
        ];
    }

    /**
     * Public map payload with navigation target resolved to zone coords or venue fallback.
     *
     * @return array{
     *   id: string,
     *   name: array{en: string, ar: string},
     *   description: array{en: ?string, ar: ?string},
     *   type: string,
     *   label: ?string,
     *   shape_type: ?string,
     *   polygon_coordinates: ?list<array{x: float, y: float}>,
     *   shape_radius: ?float,
     *   fill_color: ?string,
     *   stroke_color: ?string,
     *   opacity: ?int,
     *   stroke_width: ?int,
     *   navigate_url: ?string,
     *   lat: ?float,
     *   lng: ?float
     * }
     */
    public static function toPublicMapArray(EventZone $zone, ?float $venueLat, ?float $venueLng): array
    {
        $base = self::toArray($zone);
        $lat = $base['lat'] ?? $venueLat;
        $lng = $base['lng'] ?? $venueLng;

        $navigateUrl = $base['google_maps_url'];
        if ($navigateUrl === null && $lat !== null && $lng !== null) {
            // Directions from the visitor's current location to the zone/venue point.
            $navigateUrl = 'https://www.google.com/maps/dir/?api=1&destination='.$lat.','.$lng;
        }

        return [
            'id' => $base['id'],
            'name' => $base['name'],
            'description' => [
                'en' => $base['description_en'],
                'ar' => $base['description_ar'],
            ],
            'type' => $base['type'],
            'label' => $base['label'] ?? $base['name']['en'],
            'shape_type' => $base['shape_type'],
            'polygon_coordinates' => $base['polygon_coordinates'],
            'shape_radius' => $base['shape_radius'],
            'shape_rotation' => $base['shape_rotation'],
            'shape_radius_y' => $base['shape_radius_y'],
            'fill_color' => $base['fill_color'],
            'stroke_color' => $base['stroke_color'],
            'opacity' => $base['opacity'],
            'stroke_width' => $base['stroke_width'],
            'navigate_url' => $navigateUrl,
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    /**
     * @param  mixed  $coordinates
     * @return list<array{x: float, y: float}>|null
     */
    public static function normalizeCoordinates(mixed $coordinates): ?array
    {
        if (! is_array($coordinates) || $coordinates === []) {
            return null;
        }

        $points = [];
        foreach ($coordinates as $point) {
            if (! is_array($point)) {
                continue;
            }

            if (! array_key_exists('x', $point) || ! array_key_exists('y', $point)) {
                continue;
            }

            $x = (float) $point['x'];
            $y = (float) $point['y'];

            if ($x < 0.0 || $x > 1.0 || $y < 0.0 || $y > 1.0) {
                continue;
            }

            $points[] = [
                'x' => round($x, 6),
                'y' => round($y, 6),
            ];
        }

        return $points === [] ? null : $points;
    }

    /**
     * @return array{
     *   id: string,
     *   event_venue_id: ?string,
     *   zone_id: ?string,
     *   agenda_date: ?string,
     *   title_en: string,
     *   title_ar: string,
     *   description_en: string,
     *   description_ar: string,
     *   speaker: ?string,
     *   start_at: ?string,
     *   end_at: ?string,
     *   sort_order?: int
     * }
     */
    public static function agendaItemForTenant(EventAgendaItem $item, string $timezone): array
    {
        return [
            'id' => (string) $item->id,
            'event_venue_id' => $item->event_venue_id ? (string) $item->event_venue_id : null,
            'zone_id' => $item->zone_id ? (string) $item->zone_id : null,
            'agenda_date' => $item->agenda_date?->toDateString(),
            'title_en' => $item->title_en,
            'title_ar' => $item->title_ar,
            'description_en' => $item->description_en ?? '',
            'description_ar' => $item->description_ar ?? '',
            'speaker' => $item->speaker,
            'start_at' => EventWallClockDateTime::toInput($item->start_at, $timezone),
            'end_at' => EventWallClockDateTime::toInput($item->end_at, $timezone),
            'sort_order' => (int) $item->sort_order,
            'venue_name' => $item->relationLoaded('venue') && $item->venue
                ? ['en' => (string) $item->venue->name_en, 'ar' => (string) $item->venue->name_ar]
                : null,
            'zone_name' => $item->relationLoaded('zone') && $item->zone
                ? ['en' => (string) $item->zone->zone_name_en, 'ar' => (string) $item->zone->zone_name_ar]
                : null,
        ];
    }

    /**
     * @return array{
     *   id: string,
     *   event_venue_id: ?string,
     *   zone_id: ?string,
     *   agenda_date: ?string,
     *   title: array{en: string, ar: string},
     *   description: array{en: string, ar: string},
     *   speaker: ?string,
     *   start_at: ?string,
     *   end_at: ?string,
     *   sort_order: int
     * }
     */
    public static function agendaItemForApi(EventAgendaItem $item, string $timezone): array
    {
        $tenant = self::agendaItemForTenant($item, $timezone);

        return [
            'id' => $tenant['id'],
            'event_venue_id' => $tenant['event_venue_id'],
            'zone_id' => $tenant['zone_id'],
            'agenda_date' => $tenant['agenda_date'],
            'title' => ['en' => $tenant['title_en'], 'ar' => $tenant['title_ar']],
            'description' => ['en' => $tenant['description_en'], 'ar' => $tenant['description_ar']],
            'speaker' => $tenant['speaker'],
            'start_at' => $tenant['start_at'],
            'end_at' => $tenant['end_at'],
            'sort_order' => $tenant['sort_order'],
        ];
    }

    /**
     * @return array{
     *   id: string,
     *   title: array{en: string, ar: string},
     *   start_at: ?string,
     *   end_at: ?string,
     *   agenda_date: ?string,
     *   event_venue_id: ?string,
     *   zone_id: ?string,
     *   speaker: ?string,
     *   venue_name: ?array{en: string, ar: string},
     *   zone_name: ?array{en: string, ar: string},
     *   sort_order: int
     * }
     */
    public static function agendaItemForPublic(EventAgendaItem $item, string $timezone, ?string $fallbackVenueId = null): array
    {
        $agendaDate = $item->agenda_date?->toDateString()
            ?? ($item->start_at?->toDateString());
        $venueId = $item->event_venue_id !== null
            ? (string) $item->event_venue_id
            : $fallbackVenueId;

        return [
            'id' => (string) $item->id,
            'title' => ['en' => $item->title_en, 'ar' => $item->title_ar],
            'start_at' => EventWallClockDateTime::toIso8601($item->start_at, $timezone),
            'end_at' => EventWallClockDateTime::toIso8601($item->end_at, $timezone),
            'agenda_date' => $agendaDate,
            'event_venue_id' => $venueId,
            'zone_id' => $item->zone_id !== null ? (string) $item->zone_id : null,
            'speaker' => $item->speaker,
            'venue_name' => $item->venue
                ? ['en' => (string) $item->venue->name_en, 'ar' => (string) $item->venue->name_ar]
                : null,
            'zone_name' => $item->zone
                ? ['en' => (string) $item->zone->zone_name_en, 'ar' => (string) $item->zone->zone_name_ar]
                : null,
            'sort_order' => (int) $item->sort_order,
        ];
    }
}
