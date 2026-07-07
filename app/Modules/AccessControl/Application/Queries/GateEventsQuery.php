<?php

namespace App\Modules\AccessControl\Application\Queries;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use DateTimeInterface;

final readonly class GateEventsQuery
{
    /** @return list<AccessEvent> */
    public function list(
        string $tenantId,
        string $eventId,
        ?DateTimeInterface $since,
        int $limit,
    ): array {
        $bounded = max(1, min(200, $limit));

        return AccessEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->when($since !== null, fn ($query) => $query->where('occurred_at', '>=', $since))
            ->orderByDesc('occurred_at')
            ->limit($bounded)
            ->get()
            ->all();
    }
}
