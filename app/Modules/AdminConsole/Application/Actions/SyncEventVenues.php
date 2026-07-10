<?php

namespace App\Modules\AdminConsole\Application\Actions;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;

final class SyncEventVenues
{
    /**
     * @param  list<array<string, mixed>>  $venues
     */
    public function execute(string $tenantId, Event $event, array $venues): void
    {
        $keepIds = [];

        foreach (array_values($venues) as $index => $venue) {
            if (trim((string) ($venue['name_en'] ?? '')) === '' || trim((string) ($venue['name_ar'] ?? '')) === '') {
                continue;
            }

            $payload = [
                'tenant_id' => $tenantId,
                'event_id' => $event->id,
                'country_id' => $venue['country_id'] ?? null,
                'city_id' => $venue['city_id'] ?? null,
                'name_en' => $venue['name_en'],
                'name_ar' => $venue['name_ar'],
                'location_address' => $venue['location_address'] ?? null,
                'latitude' => $venue['latitude'] ?? null,
                'longitude' => $venue['longitude'] ?? null,
                'start_at' => $venue['start_at'] ?? null,
                'end_at' => $venue['end_at'] ?? null,
                'registration_opens_at' => $venue['registration_opens_at'] ?? null,
                'registration_closes_at' => $venue['registration_closes_at'] ?? null,
                'sort_order' => $index,
            ];

            if (! empty($venue['id'])) {
                $model = EventVenue::query()
                    ->where('tenant_id', $tenantId)
                    ->where('event_id', $event->id)
                    ->where('id', $venue['id'])
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

        EventVenue::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->when($keepIds !== [], fn ($query) => $query->whereNotIn('id', $keepIds))
            ->delete();
    }
}
