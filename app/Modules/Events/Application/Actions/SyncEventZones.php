<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
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
}
