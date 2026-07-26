<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Application\Support\EventZonePresenter;
use App\Modules\Events\Domain\EventZoneShapeType;
use App\Modules\Events\Domain\EventZoneType;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use InvalidArgumentException;

final class SyncEventZones
{
    /**
     * Upsert zones for one venue and delete missing zones on that venue only.
     *
     * @param  list<array<string, mixed>>  $zones
     */
    public function execute(string $tenantId, Event $event, int $venueId, array $zones): void
    {
        $venue = EventVenue::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->whereKey($venueId)
            ->first();

        if (! $venue instanceof EventVenue) {
            throw new InvalidArgumentException('Zone venue must belong to this event.');
        }

        $keepIds = [];
        $allowedTypes = array_fill_keys(EventZoneType::values(), true);
        $allowedShapes = array_fill_keys(EventZoneShapeType::values(), true);

        foreach (array_values($zones) as $zone) {
            if (! is_array($zone)) {
                continue;
            }

            $nameEn = trim((string) ($zone['zone_name_en'] ?? data_get($zone, 'name.en') ?? ''));
            $nameAr = trim((string) ($zone['zone_name_ar'] ?? data_get($zone, 'name.ar') ?? ''));
            $type = trim((string) ($zone['type'] ?? ''));

            if ($nameEn === '' || $nameAr === '' || $type === '') {
                continue;
            }

            if (! isset($allowedTypes[$type])) {
                throw new InvalidArgumentException('Invalid zone type.');
            }

            $capacity = array_key_exists('capacity', $zone) && $zone['capacity'] !== null && $zone['capacity'] !== ''
                ? (int) $zone['capacity']
                : null;

            if ($capacity !== null && $capacity < 0) {
                throw new InvalidArgumentException('Zone capacity must be zero or greater.');
            }

            $payload = [
                'tenant_id' => $tenantId,
                'event_id' => $event->id,
                'venue_id' => $venueId,
                'zone_name_en' => $nameEn,
                'zone_name_ar' => $nameAr,
                'type' => $type,
                'capacity' => $capacity,
            ];

            $touchesMap = array_key_exists('shape_type', $zone)
                || array_key_exists('polygon_coordinates', $zone)
                || array_key_exists('shape_radius', $zone)
                || array_key_exists('label', $zone)
                || array_key_exists('google_maps_url', $zone)
                || array_key_exists('lat', $zone)
                || array_key_exists('lng', $zone)
                || array_key_exists('fill_color', $zone)
                || array_key_exists('stroke_color', $zone)
                || array_key_exists('opacity', $zone)
                || array_key_exists('stroke_width', $zone);

            if ($touchesMap) {
                $shapeType = array_key_exists('shape_type', $zone) && $zone['shape_type'] !== null && $zone['shape_type'] !== ''
                    ? trim((string) $zone['shape_type'])
                    : null;

                if ($shapeType !== null && ! isset($allowedShapes[$shapeType])) {
                    throw new InvalidArgumentException('Invalid zone shape type.');
                }

                $coordinates = EventZonePresenter::normalizeCoordinates($zone['polygon_coordinates'] ?? null);
                if ($shapeType !== null && ($coordinates === null || $coordinates === [])) {
                    throw new InvalidArgumentException('Zone shapes require relative polygon coordinates.');
                }

                if ($shapeType === EventZoneShapeType::Circle->value) {
                    if (count($coordinates ?? []) !== 1) {
                        throw new InvalidArgumentException('Circle zones require a single relative center point.');
                    }
                } elseif ($shapeType === EventZoneShapeType::Rectangle->value) {
                    if (count($coordinates ?? []) !== 4) {
                        throw new InvalidArgumentException('Rectangle zones require four relative corner points.');
                    }
                } elseif ($shapeType === EventZoneShapeType::Polygon->value) {
                    if (count($coordinates ?? []) < 3) {
                        throw new InvalidArgumentException('Polygon zones require at least three relative points.');
                    }
                }

                $shapeRadius = array_key_exists('shape_radius', $zone) && $zone['shape_radius'] !== null && $zone['shape_radius'] !== ''
                    ? (float) $zone['shape_radius']
                    : null;

                if ($shapeType === EventZoneShapeType::Circle->value) {
                    if ($shapeRadius === null || $shapeRadius <= 0 || $shapeRadius > 1) {
                        throw new InvalidArgumentException('Circle zones require a relative radius between 0 and 1.');
                    }
                } else {
                    $shapeRadius = null;
                }

                $label = array_key_exists('label', $zone) && $zone['label'] !== null
                    ? trim((string) $zone['label'])
                    : null;
                $label = $label === '' ? null : $label;

                $googleMapsUrl = array_key_exists('google_maps_url', $zone) && $zone['google_maps_url'] !== null
                    ? trim((string) $zone['google_maps_url'])
                    : null;
                $googleMapsUrl = $googleMapsUrl === '' ? null : $googleMapsUrl;

                $lat = array_key_exists('lat', $zone) && $zone['lat'] !== null && $zone['lat'] !== ''
                    ? (float) $zone['lat']
                    : null;
                $lng = array_key_exists('lng', $zone) && $zone['lng'] !== null && $zone['lng'] !== ''
                    ? (float) $zone['lng']
                    : null;

                $fillColor = self::nullableColor($zone['fill_color'] ?? null);
                $strokeColor = self::nullableColor($zone['stroke_color'] ?? null);

                $opacity = array_key_exists('opacity', $zone) && $zone['opacity'] !== null && $zone['opacity'] !== ''
                    ? (int) $zone['opacity']
                    : null;
                if ($opacity !== null && ($opacity < 0 || $opacity > 100)) {
                    throw new InvalidArgumentException('Zone opacity must be between 0 and 100.');
                }

                $strokeWidth = array_key_exists('stroke_width', $zone) && $zone['stroke_width'] !== null && $zone['stroke_width'] !== ''
                    ? (int) $zone['stroke_width']
                    : null;
                if ($strokeWidth !== null && ($strokeWidth < 0 || $strokeWidth > 20)) {
                    throw new InvalidArgumentException('Zone stroke width must be between 0 and 20.');
                }

                $payload = [
                    ...$payload,
                    'shape_type' => $shapeType,
                    'polygon_coordinates' => $coordinates,
                    'shape_radius' => $shapeRadius,
                    'label' => $label,
                    'google_maps_url' => $googleMapsUrl,
                    'lat' => $lat,
                    'lng' => $lng,
                    'fill_color' => $fillColor,
                    'stroke_color' => $strokeColor,
                    'opacity' => $opacity,
                    'stroke_width' => $strokeWidth,
                ];
            }

            if (! empty($zone['id'])) {
                $model = EventZone::query()
                    ->where('tenant_id', $tenantId)
                    ->where('event_id', $event->id)
                    ->where('venue_id', $venueId)
                    ->where('id', $zone['id'])
                    ->first();

                if ($model instanceof EventZone) {
                    $model->fill($payload)->save();
                    $keepIds[] = $model->id;

                    continue;
                }
            }

            $created = EventZone::query()->create($payload);
            $keepIds[] = $created->id;
        }

        EventZone::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->where('venue_id', $venueId)
            ->when($keepIds !== [], fn ($query) => $query->whereNotIn('id', $keepIds))
            ->delete();
    }

    private static function nullableColor(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $color = trim((string) $value);
        if ($color === '') {
            return null;
        }

        if (! preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $color)) {
            throw new InvalidArgumentException('Zone colors must be hex values.');
        }

        return $color;
    }
}
