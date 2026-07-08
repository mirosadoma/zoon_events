<?php

namespace App\Modules\AccessControl\Application\Actions;

use App\Modules\AccessControl\Domain\Events\EmergencyCleared;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\EmergencyEvent;
use App\Modules\Audit\Application\AuditedTransaction;
use DateTimeInterface;
use Illuminate\Support\Collection;

final readonly class ClearEmergencyAction
{
    public function __construct(private AuditedTransaction $audited) {}

    /** @return Collection<int, EmergencyEvent> */
    public function execute(
        string $tenantId,
        string $eventId,
        ?string $zoneId,
        DateTimeInterface $clearedAt,
    ): Collection {
        return $this->audited->run(
            function () use ($tenantId, $eventId, $zoneId, $clearedAt): Collection {
                $active = EmergencyEvent::query()
                    ->where('tenant_id', $tenantId)
                    ->where('event_id', $eventId)
                    ->whereNull('cleared_at')
                    ->when($zoneId !== null, fn ($query) => $query->where(function ($inner) use ($zoneId): void {
                        $inner->whereNull('zone_id')->orWhere('zone_id', $zoneId);
                    }))
                    ->get();

                foreach ($active as $emergency) {
                    $emergency->forceFill(['cleared_at' => $clearedAt])->save();
                }

                AccessEvent::query()->create([
                    'tenant_id' => $tenantId,
                    'event_id' => $eventId,
                    'event_type' => 'emergency',
                    'zone_id' => $zoneId,
                    'direction' => 'none',
                    'decision' => 'n/a',
                    'reason_code' => null,
                    'source' => 'acs_gate',
                    'occurred_at' => $clearedAt,
                ]);

                return $active;
            },
            function (Collection $cleared) use ($tenantId, $eventId, $zoneId): void {
                foreach ($cleared as $emergency) {
                    event(new EmergencyCleared($tenantId, $eventId, $emergency->id, $zoneId));
                }
            },
        );
    }
}
