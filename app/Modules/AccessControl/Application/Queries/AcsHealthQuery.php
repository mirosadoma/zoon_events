<?php

namespace App\Modules\AccessControl\Application\Queries;

use App\Modules\AccessControl\Application\Support\EmergencyStateService;
use App\Modules\AccessControl\Contracts\AcsAdapter;
use App\Modules\AccessControl\Domain\AcsLaneHealthDeriver;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\Shared\Contracts\Clock;

final readonly class AcsHealthQuery
{
    public function __construct(
        private AcsAdapter $adapter,
        private EmergencyStateService $emergencyState,
        private AcsLaneHealthDeriver $deriver,
        private Clock $clock,
    ) {}

    /** @return array{integration_status: string, active_emergency: bool, lanes: list<array{lane_id: string, health_status: string, last_seen_at: string|null}>} */
    public function summary(string $tenantId, string $eventId): array
    {
        $threshold = (int) config('acs.lane.offline_threshold_seconds', 120);
        $now = $this->clock->now();

        $lanes = AcsLane::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->get()
            ->map(fn (AcsLane $lane): array => [
                'lane_id' => $lane->id,
                'health_status' => $this->deriver->derive($lane, $threshold, $now),
                'last_seen_at' => $lane->last_seen_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'integration_status' => $this->adapter->health()->status,
            'active_emergency' => $this->emergencyState->hasActiveEmergency($tenantId, $eventId),
            'lanes' => $lanes,
        ];
    }
}
