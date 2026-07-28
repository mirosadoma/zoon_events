<?php

namespace App\Modules\Events\Application\Support;

/**
 * Convert relative floor-plan points to lat/lng using geographic overlay bounds,
 * or a default box around a venue center.
 */
final class RelativeToGeoCoordinates
{
    /**
     * @param  list<array{x?: mixed, y?: mixed}>  $points
     * @return list<array{lat: float, lng: float}>
     */
    public static function convertPoints(
        array $points,
        ?float $north,
        ?float $south,
        ?float $east,
        ?float $west,
        ?float $fallbackLat = null,
        ?float $fallbackLng = null,
        float $fallbackHalfSpanDegrees = 0.0015,
    ): array {
        [$resolvedNorth, $resolvedSouth, $resolvedEast, $resolvedWest] = self::resolveBounds(
            $north,
            $south,
            $east,
            $west,
            $fallbackLat,
            $fallbackLng,
            $fallbackHalfSpanDegrees,
        );

        $converted = [];
        foreach ($points as $point) {
            if (! is_array($point) || ! array_key_exists('x', $point) || ! array_key_exists('y', $point)) {
                continue;
            }

            $x = max(0.0, min(1.0, (float) $point['x']));
            $y = max(0.0, min(1.0, (float) $point['y']));

            $converted[] = [
                'lat' => round($resolvedNorth + ($resolvedSouth - $resolvedNorth) * $y, 7),
                'lng' => round($resolvedWest + ($resolvedEast - $resolvedWest) * $x, 7),
            ];
        }

        return $converted;
    }

    public static function convertRadiusMeters(
        float $relativeRadius,
        ?float $north,
        ?float $south,
        ?float $east,
        ?float $west,
        ?float $fallbackLat = null,
        ?float $fallbackLng = null,
        float $fallbackHalfSpanDegrees = 0.0015,
    ): float {
        [$resolvedNorth, $resolvedSouth, $resolvedEast, $resolvedWest] = self::resolveBounds(
            $north,
            $south,
            $east,
            $west,
            $fallbackLat,
            $fallbackLng,
            $fallbackHalfSpanDegrees,
        );

        $centerLat = ($resolvedNorth + $resolvedSouth) / 2.0;
        $widthMeters = self::haversineMeters($centerLat, $resolvedWest, $centerLat, $resolvedEast);
        $heightMeters = self::haversineMeters($resolvedNorth, $resolvedWest, $resolvedSouth, $resolvedWest);
        $span = max($widthMeters, $heightMeters, 1.0);

        return max(0.5, round($relativeRadius * $span, 2));
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float} north, south, east, west
     */
    public static function resolveBounds(
        ?float $north,
        ?float $south,
        ?float $east,
        ?float $west,
        ?float $fallbackLat,
        ?float $fallbackLng,
        float $fallbackHalfSpanDegrees = 0.0015,
    ): array {
        if (
            $north !== null && $south !== null && $east !== null && $west !== null
            && $north > $south && $east != $west
        ) {
            return [$north, $south, $east, $west];
        }

        $lat = $fallbackLat ?? 0.0;
        $lng = $fallbackLng ?? 0.0;
        $span = max(0.0003, $fallbackHalfSpanDegrees);

        return [
            $lat + $span,
            $lat - $span,
            $lng + $span,
            $lng - $span,
        ];
    }

    /**
     * Approximate viewport bounds from center + zoom (Web Mercator heuristic).
     *
     * @return array{north: float, south: float, east: float, west: float}
     */
    public static function boundsFromCamera(
        float $lat,
        float $lng,
        float $zoom,
        float $aspect = 1.6,
    ): array {
        $zoom = max(1.0, min(22.0, $zoom));
        $latSpan = 360.0 / (2 ** $zoom) * 0.55;
        $lngSpan = $latSpan * max(0.5, $aspect);

        return [
            'north' => round($lat + $latSpan / 2, 7),
            'south' => round($lat - $latSpan / 2, 7),
            'east' => round($lng + $lngSpan / 2, 7),
            'west' => round($lng - $lngSpan / 2, 7),
        ];
    }

    private static function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1.0, sqrt($a)));
    }
}
