<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\Events\Application\Support\EventWallClockDateTime;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventAgendaItem;

final class SyncEventAgendaItems
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function execute(string $tenantId, Event $event, array $items): void
    {
        $keepIds = [];

        foreach (array_values($items) as $index => $item) {
            if (trim((string) ($item['title_en'] ?? '')) === '' || trim((string) ($item['title_ar'] ?? '')) === '') {
                continue;
            }

            if (empty($item['start_at'])) {
                continue;
            }

            $payload = [
                'tenant_id' => $tenantId,
                'event_id' => $event->id,
                'title_en' => trim((string) $item['title_en']),
                'title_ar' => trim((string) $item['title_ar']),
                'start_at' => EventWallClockDateTime::parseToAppStorage(
                    isset($item['start_at']) ? (string) $item['start_at'] : null,
                    (string) $event->timezone,
                )?->toDateTimeString(),
                'end_at' => EventWallClockDateTime::parseToAppStorage(
                    isset($item['end_at']) ? (string) $item['end_at'] : null,
                    (string) $event->timezone,
                )?->toDateTimeString(),
                'sort_order' => $index,
            ];

            if ($payload['start_at'] === null) {
                continue;
            }

            if (! empty($item['id'])) {
                $model = EventAgendaItem::query()
                    ->where('tenant_id', $tenantId)
                    ->where('event_id', $event->id)
                    ->where('id', $item['id'])
                    ->first();

                if ($model instanceof EventAgendaItem) {
                    $model->fill($payload)->save();
                    $keepIds[] = $model->id;

                    continue;
                }
            }

            $created = EventAgendaItem::query()->create($payload);
            $keepIds[] = $created->id;
        }

        EventAgendaItem::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->when($keepIds !== [], fn ($query) => $query->whereNotIn('id', $keepIds))
            ->delete();
    }
}
