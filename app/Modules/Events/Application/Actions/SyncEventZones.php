<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Application\Support\EventZonePresenter;
use App\Modules\Events\Application\Support\ZoneScannerCode;
use App\Modules\Events\Domain\EventCoordinateSpace;
use App\Modules\Events\Domain\EventZoneFloorType;
use App\Modules\Events\Domain\EventZoneShapeType;
use App\Modules\Events\Domain\EventZoneType;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use Illuminate\Support\Facades\Storage;
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
        $allowedFloorTypes = array_fill_keys(EventZoneFloorType::values(), true);

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

            $floorType = array_key_exists('floor_type', $zone) && $zone['floor_type'] !== null && $zone['floor_type'] !== ''
                ? trim((string) $zone['floor_type'])
                : null;
            if ($floorType !== null && ! isset($allowedFloorTypes[$floorType])) {
                throw new InvalidArgumentException('Invalid zone floor type.');
            }

            $floorNumber = null;
            if ($floorType === EventZoneFloorType::Floor->value) {
                if (! array_key_exists('floor_number', $zone) || $zone['floor_number'] === null || $zone['floor_number'] === '') {
                    throw new InvalidArgumentException('Floor number is required when floor type is floor.');
                }
                $floorNumber = (int) $zone['floor_number'];
                if ($floorNumber < 0 || $floorNumber > 500) {
                    throw new InvalidArgumentException('Floor number must be between 0 and 500.');
                }
            }

            $descriptionEn = array_key_exists('description_en', $zone) && $zone['description_en'] !== null
                ? trim((string) $zone['description_en'])
                : null;
            $descriptionEn = $descriptionEn === '' ? null : $descriptionEn;

            $descriptionAr = array_key_exists('description_ar', $zone) && $zone['description_ar'] !== null
                ? trim((string) $zone['description_ar'])
                : null;
            $descriptionAr = $descriptionAr === '' ? null : $descriptionAr;

            $payload = [
                'tenant_id' => $tenantId,
                'event_id' => $event->id,
                'venue_id' => $venueId,
                'zone_name_en' => $nameEn,
                'zone_name_ar' => $nameAr,
                'type' => $type,
                'capacity' => $capacity,
            ];

            $incomingCode = ZoneScannerCode::normalize(
                array_key_exists('scanner_code', $zone) ? (string) ($zone['scanner_code'] ?? '') : null,
            );

            if (array_key_exists('floor_type', $zone)) {
                $payload['floor_type'] = $floorType;
                $payload['floor_number'] = $floorNumber;
            }

            if (array_key_exists('description_en', $zone)) {
                $payload['description_en'] = $descriptionEn;
            }

            if (array_key_exists('description_ar', $zone)) {
                $payload['description_ar'] = $descriptionAr;
            }

            $touchesMap = array_key_exists('shape_type', $zone)
                || array_key_exists('polygon_coordinates', $zone)
                || array_key_exists('shape_radius', $zone)
                || array_key_exists('shape_rotation', $zone)
                || array_key_exists('shape_radius_y', $zone)
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
                    throw new InvalidArgumentException('Zone shapes require polygon coordinates.');
                }

                $shapeType = EventZonePresenter::coerceShapeTypeForCoordinates($shapeType, $coordinates);

                $coordinateSpace = array_key_exists('coordinate_space', $zone) && $zone['coordinate_space'] !== null && $zone['coordinate_space'] !== ''
                    ? (string) $zone['coordinate_space']
                    : EventZonePresenter::detectCoordinateSpace($zone['polygon_coordinates'] ?? null);
                $isGeo = $coordinateSpace === EventCoordinateSpace::Geo->value;

                if (
                    $shapeType === EventZoneShapeType::Circle->value
                    || $shapeType === EventZoneShapeType::Ellipse->value
                    || $shapeType === EventZoneShapeType::Pillar->value
                    || $shapeType === EventZoneShapeType::Person->value
                ) {
                    if (count($coordinates ?? []) !== 1) {
                        throw new InvalidArgumentException('Circle/ellipse/marker zones require a single center point.');
                    }
                } elseif ($shapeType === EventZoneShapeType::Rectangle->value) {
                    if (count($coordinates ?? []) !== 4) {
                        throw new InvalidArgumentException('Rectangle zones require four corner points.');
                    }
                } elseif ($shapeType === EventZoneShapeType::Triangle->value) {
                    if (count($coordinates ?? []) !== 3) {
                        throw new InvalidArgumentException('Triangle zones require three points.');
                    }
                } elseif ($shapeType === EventZoneShapeType::Hexagon->value) {
                    if (count($coordinates ?? []) !== 6) {
                        throw new InvalidArgumentException('Hexagon zones require six points.');
                    }
                } elseif ($shapeType === EventZoneShapeType::Polygon->value) {
                    if (count($coordinates ?? []) < 3) {
                        throw new InvalidArgumentException('Polygon zones require at least three points.');
                    }
                }

                $shapeRadius = array_key_exists('shape_radius', $zone) && $zone['shape_radius'] !== null && $zone['shape_radius'] !== ''
                    ? (float) $zone['shape_radius']
                    : null;

                $shapeRadiusY = array_key_exists('shape_radius_y', $zone) && $zone['shape_radius_y'] !== null && $zone['shape_radius_y'] !== ''
                    ? (float) $zone['shape_radius_y']
                    : null;

                $shapeRotation = array_key_exists('shape_rotation', $zone) && $zone['shape_rotation'] !== null && $zone['shape_rotation'] !== ''
                    ? (float) $zone['shape_rotation']
                    : 0.0;

                if (
                    $shapeType === EventZoneShapeType::Circle->value
                    || $shapeType === EventZoneShapeType::Ellipse->value
                    || $shapeType === EventZoneShapeType::Pillar->value
                    || $shapeType === EventZoneShapeType::Person->value
                ) {
                    $maxRadius = $isGeo ? 50000.0 : 1.0;
                    if ($shapeRadius === null || $shapeRadius <= 0 || $shapeRadius > $maxRadius) {
                        throw new InvalidArgumentException(
                            $isGeo
                                ? 'Circle/ellipse/marker zones require a radius in meters (0–50000).'
                                : 'Circle/ellipse/marker zones require a relative radius between 0 and 1.',
                        );
                    }
                    if ($shapeType === EventZoneShapeType::Ellipse->value) {
                        if ($shapeRadiusY === null || $shapeRadiusY <= 0 || $shapeRadiusY > $maxRadius) {
                            $shapeRadiusY = $shapeRadius;
                        }
                    } else {
                        $shapeRadiusY = null;
                    }
                } else {
                    $shapeRadius = null;
                    $shapeRadiusY = null;
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
                    'coordinate_space' => $coordinateSpace,
                    'polygon_coordinates' => $coordinates,
                    'shape_radius' => $shapeRadius,
                    'shape_rotation' => $shapeRotation,
                    'shape_radius_y' => $shapeRadiusY,
                    'label' => $label,
                    'google_maps_url' => $googleMapsUrl,
                    'lat' => $lat,
                    'lng' => $lng,
                    'fill_color' => $fillColor,
                    'stroke_color' => $strokeColor,
                    'opacity' => $opacity,
                    'stroke_width' => $strokeWidth,
                ];

                // Color mode clears image fill (mutually exclusive).
                if ($fillColor !== null) {
                    $payload['fill_image_path'] = null;
                }
            }

            if (! empty($zone['id'])) {
                $model = EventZone::query()
                    ->where('tenant_id', $tenantId)
                    ->where('event_id', $event->id)
                    ->where('venue_id', $venueId)
                    ->where('id', $zone['id'])
                    ->first();

                if ($model instanceof EventZone) {
                    if ($incomingCode !== null) {
                        ZoneScannerCode::assertUnique($tenantId, (string) $event->id, $incomingCode, (int) $model->id);
                        $payload['scanner_code'] = $incomingCode;
                    } elseif ($model->scanner_code === null || $model->scanner_code === '') {
                        $payload['scanner_code'] = ZoneScannerCode::uniqueForEvent($tenantId, (string) $event->id, (int) $model->id);
                    }

                    if (
                        array_key_exists('fill_image_path', $payload)
                        && $payload['fill_image_path'] === null
                        && $model->fill_image_path !== null
                        && $model->fill_image_path !== ''
                    ) {
                        Storage::disk('public')->delete($model->fill_image_path);
                    }

                    $model->fill($payload)->save();
                    $keepIds[] = $model->id;

                    continue;
                }
            }

            if ($incomingCode !== null) {
                ZoneScannerCode::assertUnique($tenantId, (string) $event->id, $incomingCode);
                $payload['scanner_code'] = $incomingCode;
            } else {
                $payload['scanner_code'] = ZoneScannerCode::uniqueForEvent($tenantId, (string) $event->id);
            }

            $created = EventZone::query()->create($payload);
            $keepIds[] = $created->id;
        }

        EventZone::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->where('venue_id', $venueId)
            ->when($keepIds !== [], fn ($query) => $query->whereNotIn('id', $keepIds))
            ->get()
            ->each(function (EventZone $zone): void {
                if ($zone->fill_image_path !== null && $zone->fill_image_path !== '') {
                    Storage::disk('public')->delete($zone->fill_image_path);
                }
                $zone->delete();
            });
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
