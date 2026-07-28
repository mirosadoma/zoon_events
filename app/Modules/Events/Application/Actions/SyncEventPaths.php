<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Application\Support\EventZonePresenter;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventPath;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use InvalidArgumentException;

final class SyncEventPaths
{
    /**
     * Upsert paths for one venue and delete missing paths on that venue only.
     *
     * @param  list<array<string, mixed>>  $paths
     */
    public function execute(string $tenantId, Event $event, int $venueId, array $paths): void
    {
        $venue = EventVenue::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->whereKey($venueId)
            ->first();

        if (! $venue instanceof EventVenue) {
            throw new InvalidArgumentException('Path venue must belong to this event.');
        }

        $zoneIds = EventZone::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->where('venue_id', $venueId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $allowedZones = array_fill_keys($zoneIds, true);

        $keepIds = [];

        foreach (array_values($paths) as $index => $path) {
            if (! is_array($path)) {
                continue;
            }

            $coordinates = EventZonePresenter::normalizeCoordinates($path['polyline_coordinates'] ?? null);
            if ($coordinates === null || count($coordinates) < 2) {
                throw new InvalidArgumentException('Paths require at least two points.');
            }

            $coordinateSpace = array_key_exists('coordinate_space', $path) && $path['coordinate_space'] !== null && $path['coordinate_space'] !== ''
                ? (string) $path['coordinate_space']
                : EventZonePresenter::detectCoordinateSpace($path['polyline_coordinates'] ?? null);

            $nameEn = array_key_exists('name_en', $path) && $path['name_en'] !== null
                ? trim((string) $path['name_en'])
                : '';
            $nameAr = array_key_exists('name_ar', $path) && $path['name_ar'] !== null
                ? trim((string) $path['name_ar'])
                : '';
            $nameEn = $nameEn === '' ? null : $nameEn;
            $nameAr = $nameAr === '' ? null : $nameAr;

            $fromZoneId = array_key_exists('from_zone_id', $path) && $path['from_zone_id'] !== null && $path['from_zone_id'] !== ''
                ? (int) $path['from_zone_id']
                : null;
            $toZoneId = array_key_exists('to_zone_id', $path) && $path['to_zone_id'] !== null && $path['to_zone_id'] !== ''
                ? (int) $path['to_zone_id']
                : null;

            if ($fromZoneId !== null && ! isset($allowedZones[$fromZoneId])) {
                throw new InvalidArgumentException('Path from_zone must belong to this venue.');
            }
            if ($toZoneId !== null && ! isset($allowedZones[$toZoneId])) {
                throw new InvalidArgumentException('Path to_zone must belong to this venue.');
            }

            $strokeColor = self::nullableColor($path['stroke_color'] ?? null);

            $strokeWidth = array_key_exists('stroke_width', $path) && $path['stroke_width'] !== null && $path['stroke_width'] !== ''
                ? (int) $path['stroke_width']
                : 3;
            if ($strokeWidth < 1 || $strokeWidth > 20) {
                throw new InvalidArgumentException('Path stroke width must be between 1 and 20.');
            }

            $opacity = array_key_exists('opacity', $path) && $path['opacity'] !== null && $path['opacity'] !== ''
                ? (int) $path['opacity']
                : 85;
            if ($opacity < 0 || $opacity > 100) {
                throw new InvalidArgumentException('Path opacity must be between 0 and 100.');
            }

            $sortOrder = array_key_exists('sort_order', $path) && $path['sort_order'] !== null && $path['sort_order'] !== ''
                ? (int) $path['sort_order']
                : $index;

            $payload = [
                'tenant_id' => $tenantId,
                'event_id' => $event->id,
                'venue_id' => $venueId,
                'name_en' => $nameEn,
                'name_ar' => $nameAr,
                'polyline_coordinates' => $coordinates,
                'coordinate_space' => $coordinateSpace,
                'from_zone_id' => $fromZoneId,
                'to_zone_id' => $toZoneId,
                'stroke_color' => $strokeColor ?? '#2563eb',
                'stroke_width' => $strokeWidth,
                'opacity' => $opacity,
                'sort_order' => max(0, $sortOrder),
            ];

            if (! empty($path['id'])) {
                $model = EventPath::query()
                    ->where('tenant_id', $tenantId)
                    ->where('event_id', $event->id)
                    ->where('venue_id', $venueId)
                    ->where('id', $path['id'])
                    ->first();

                if ($model instanceof EventPath) {
                    $model->fill($payload)->save();
                    $keepIds[] = $model->id;

                    continue;
                }
            }

            $created = EventPath::query()->create($payload);
            $keepIds[] = $created->id;
        }

        EventPath::query()
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
            throw new InvalidArgumentException('Path colors must be hex values.');
        }

        return $color;
    }
}
