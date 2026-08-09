<?php

namespace App\Modules\AdminConsole\Application\Actions;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Application\Support\EventWallClockDateTime;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use InvalidArgumentException;

final class SyncEventVenues
{
    /**
     * @param  list<array<string, mixed>>  $venues
     */
    public function execute(string $tenantId, Event $event, array $venues): void
    {
        $keepIds = [];
        $normalized = [];
        $timezone = (string) $event->timezone;

        foreach (array_values($venues) as $index => $venue) {
            if (! is_array($venue)) {
                continue;
            }

            $nameEn = trim((string) ($venue['name_en'] ?? data_get($venue, 'name.en') ?? ''));
            $nameAr = trim((string) ($venue['name_ar'] ?? data_get($venue, 'name.ar') ?? ''));

            if ($nameEn === '' || $nameAr === '') {
                continue;
            }

            $normalized[] = [
                'id' => $venue['id'] ?? null,
                'country_id' => $venue['country_id'] ?? null,
                'city_id' => $venue['city_id'] ?? null,
                'name_en' => $nameEn,
                'name_ar' => $nameAr,
                'location_address' => $venue['location_address'] ?? null,
                'latitude' => $venue['latitude'] ?? null,
                'longitude' => $venue['longitude'] ?? null,
                'start_at' => EventWallClockDateTime::parseToAppStorage(
                    isset($venue['start_at']) ? (string) $venue['start_at'] : null,
                    $timezone,
                )?->toDateTimeString(),
                'end_at' => EventWallClockDateTime::parseToAppStorage(
                    isset($venue['end_at']) ? (string) $venue['end_at'] : null,
                    $timezone,
                )?->toDateTimeString(),
                'registration_opens_at' => EventWallClockDateTime::parseToAppStorage(
                    isset($venue['registration_opens_at']) ? (string) $venue['registration_opens_at'] : null,
                    $timezone,
                )?->toDateTimeString(),
                'registration_closes_at' => EventWallClockDateTime::parseToAppStorage(
                    isset($venue['registration_closes_at']) ? (string) $venue['registration_closes_at'] : null,
                    $timezone,
                )?->toDateTimeString(),
                'sort_order' => $index,
            ];
        }

        if ($venues !== [] && $normalized === []) {
            throw new InvalidArgumentException('No valid venues were provided.');
        }

        foreach ($normalized as $payload) {
            $payload['tenant_id'] = $tenantId;
            $payload['event_id'] = $event->id;
            $existingId = $payload['id'] ?? null;
            unset($payload['id']);

            if (! empty($existingId)) {
                $model = EventVenue::query()
                    ->where('tenant_id', $tenantId)
                    ->where('event_id', $event->id)
                    ->where('id', $existingId)
                    ->first();

                if ($model instanceof EventVenue) {
                    $model->fill($payload)->save();
                    $keepIds[] = $model->id;

                    continue;
                }
            }

            $created = EventVenue::query()->create($payload);
            $keepIds[] = $created->id;
        }

        $deleteQuery = EventVenue::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id);

        if ($keepIds !== []) {
            $deleteQuery->whereNotIn('id', $keepIds);
        }

        $deleteQuery->delete();

        $this->syncEventSchedule($event);
    }

    private function syncEventSchedule(Event $event): void
    {
        $rows = EventVenue::query()
            ->where('event_id', $event->id)
            ->whereNotNull('start_at')
            ->whereNotNull('end_at')
            ->whereNotNull('registration_opens_at')
            ->whereNotNull('registration_closes_at')
            ->get(['start_at', 'end_at', 'registration_opens_at', 'registration_closes_at']);

        if ($rows->isEmpty()) {
            return;
        }

        $event->forceFill([
            'start_at' => $rows->min('start_at'),
            'end_at' => $rows->max('end_at'),
            'registration_opens_at' => $rows->min('registration_opens_at'),
            'registration_closes_at' => $rows->max('registration_closes_at'),
        ])->save();
    }
}
