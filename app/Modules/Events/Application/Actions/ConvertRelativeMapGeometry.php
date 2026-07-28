<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\Events\Application\Support\EventZonePresenter;
use App\Modules\Events\Application\Support\RelativeToGeoCoordinates;
use App\Modules\Events\Domain\EventCoordinateSpace;
use App\Modules\Events\Domain\EventZoneShapeType;
use App\Modules\Events\Infrastructure\Persistence\Models\EventPath;
use App\Modules\Events\Infrastructure\Persistence\Models\EventVenueMap;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;

/**
 * One-time conversion of relative floor-plan geometry to geo lat/lng
 * when overlay bounds (or camera) are available.
 */
final class ConvertRelativeMapGeometry
{
    public function execute(EventVenueMap $map): int
    {
        $north = $map->overlay_north !== null ? (float) $map->overlay_north : null;
        $south = $map->overlay_south !== null ? (float) $map->overlay_south : null;
        $east = $map->overlay_east !== null ? (float) $map->overlay_east : null;
        $west = $map->overlay_west !== null ? (float) $map->overlay_west : null;
        $fallbackLat = $map->map_center_lat !== null ? (float) $map->map_center_lat : null;
        $fallbackLng = $map->map_center_lng !== null ? (float) $map->map_center_lng : null;

        if (
            ($north === null || $south === null || $east === null || $west === null)
            && ($fallbackLat === null || $fallbackLng === null)
        ) {
            return 0;
        }

        $converted = 0;

        $zones = EventZone::query()
            ->where('tenant_id', $map->tenant_id)
            ->where('event_id', $map->event_id)
            ->where('venue_id', $map->venue_id)
            ->where(function ($query): void {
                $query->whereNull('coordinate_space')
                    ->orWhere('coordinate_space', EventCoordinateSpace::Relative->value);
            })
            ->get();

        foreach ($zones as $zone) {
            $points = is_array($zone->polygon_coordinates) ? $zone->polygon_coordinates : [];
            if ($points === []) {
                continue;
            }

            if (EventZonePresenter::detectCoordinateSpace($points) === EventCoordinateSpace::Geo->value) {
                $zone->forceFill(['coordinate_space' => EventCoordinateSpace::Geo->value])->save();
                $converted++;

                continue;
            }

            $geoPoints = RelativeToGeoCoordinates::convertPoints(
                $points,
                $north,
                $south,
                $east,
                $west,
                $fallbackLat,
                $fallbackLng,
            );

            if ($geoPoints === []) {
                continue;
            }

            $shapeType = $zone->shape_type instanceof EventZoneShapeType
                ? $zone->shape_type->value
                : ($zone->shape_type !== null ? (string) $zone->shape_type : null);

            $isRadiusShape = in_array($shapeType, [
                EventZoneShapeType::Circle->value,
                EventZoneShapeType::Ellipse->value,
                EventZoneShapeType::Pillar->value,
                EventZoneShapeType::Person->value,
            ], true);

            $payload = [
                'coordinate_space' => EventCoordinateSpace::Geo->value,
                'polygon_coordinates' => $geoPoints,
            ];

            if ($isRadiusShape && $zone->shape_radius !== null) {
                $payload['shape_radius'] = RelativeToGeoCoordinates::convertRadiusMeters(
                    (float) $zone->shape_radius,
                    $north,
                    $south,
                    $east,
                    $west,
                    $fallbackLat,
                    $fallbackLng,
                );
            }
            if ($isRadiusShape && $zone->shape_radius_y !== null) {
                $payload['shape_radius_y'] = RelativeToGeoCoordinates::convertRadiusMeters(
                    (float) $zone->shape_radius_y,
                    $north,
                    $south,
                    $east,
                    $west,
                    $fallbackLat,
                    $fallbackLng,
                );
            }

            $zone->forceFill($payload)->save();
            $converted++;
        }

        $paths = EventPath::query()
            ->where('tenant_id', $map->tenant_id)
            ->where('event_id', $map->event_id)
            ->where('venue_id', $map->venue_id)
            ->where(function ($query): void {
                $query->whereNull('coordinate_space')
                    ->orWhere('coordinate_space', EventCoordinateSpace::Relative->value);
            })
            ->get();

        foreach ($paths as $path) {
            $points = is_array($path->polyline_coordinates) ? $path->polyline_coordinates : [];
            if ($points === []) {
                continue;
            }

            if (EventZonePresenter::detectCoordinateSpace($points) === EventCoordinateSpace::Geo->value) {
                $path->forceFill(['coordinate_space' => EventCoordinateSpace::Geo->value])->save();
                $converted++;

                continue;
            }

            $geoPoints = RelativeToGeoCoordinates::convertPoints(
                $points,
                $north,
                $south,
                $east,
                $west,
                $fallbackLat,
                $fallbackLng,
            );

            if ($geoPoints === []) {
                continue;
            }

            $path->forceFill([
                'coordinate_space' => EventCoordinateSpace::Geo->value,
                'polyline_coordinates' => $geoPoints,
            ])->save();
            $converted++;
        }

        return $converted;
    }
}
