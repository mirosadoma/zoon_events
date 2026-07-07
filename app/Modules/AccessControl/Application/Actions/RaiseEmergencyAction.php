<?php

namespace App\Modules\AccessControl\Application\Actions;

use App\Modules\AccessControl\Application\Support\EmergencyStateService;
use App\Modules\AccessControl\Domain\Events\EmergencyRaised;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\EmergencyEvent;
use App\Modules\Audit\Application\AuditedTransaction;
use DateTimeInterface;
use Illuminate\Support\Str;

final readonly class RaiseEmergencyAction
{
    public function __construct(
        private EmergencyStateService $emergencyState,
        private AuditedTransaction $audited,
    ) {}

    public function execute(
        string $tenantId,
        string $eventId,
        ?string $zoneId,
        string $signalSource,
        DateTimeInterface $raisedAt,
    ): EmergencyEvent {
        $behavior = $this->deriveBehaviorApplied($tenantId, $eventId, $zoneId);

        return $this->audited->run(
            function () use ($tenantId, $eventId, $zoneId, $signalSource, $raisedAt, $behavior): EmergencyEvent {
                $emergency = EmergencyEvent::query()->create([
                    'id' => (string) Str::ulid(),
                    'tenant_id' => $tenantId,
                    'event_id' => $eventId,
                    'zone_id' => $zoneId,
                    'signal_source' => $signalSource,
                    'behavior_applied' => $behavior,
                    'raised_at' => $raisedAt,
                ]);

                AccessEvent::query()->create([
                    'id' => (string) Str::ulid(),
                    'tenant_id' => $tenantId,
                    'event_id' => $eventId,
                    'event_type' => 'emergency',
                    'zone_id' => $zoneId,
                    'direction' => 'none',
                    'decision' => 'n/a',
                    'reason_code' => null,
                    'source' => 'acs_gate',
                    'occurred_at' => $raisedAt,
                ]);

                return $emergency;
            },
            fn (EmergencyEvent $emergency): mixed => event(new EmergencyRaised(
                $tenantId,
                $eventId,
                $emergency->id,
                $zoneId,
            )),
        );
    }

    private function deriveBehaviorApplied(string $tenantId, string $eventId, ?string $zoneId): string
    {
        if ($zoneId !== null) {
            return (string) AcsZone::query()
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('id', $zoneId)
                ->valueOrFail('emergency_egress_mode');
        }

        $modes = AcsZone::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'active')
            ->distinct()
            ->pluck('emergency_egress_mode')
            ->values();

        if ($modes->isEmpty()) {
            return 'fail_open';
        }

        if ($modes->count() === 1) {
            return (string) $modes->first();
        }

        return 'mixed';
    }
}
