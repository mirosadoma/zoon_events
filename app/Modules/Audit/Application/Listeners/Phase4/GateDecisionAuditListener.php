<?php

namespace App\Modules\Audit\Application\Listeners\Phase4;

use App\Modules\AccessControl\Domain\Events\GateAuthorized;
use App\Modules\AccessControl\Domain\Events\GateDenied;
use App\Modules\Audit\Contracts\AuditWriter;

final readonly class GateDecisionAuditListener
{
    public function __construct(private AuditWriter $audit) {}

    public function handleAuthorized(GateAuthorized $event): void
    {
        $this->write($event, 'access.authorized', 'succeeded');
    }

    public function handleDenied(GateDenied $event): void
    {
        $this->write($event, 'access.denied', 'denied');
    }

    private function write(GateAuthorized|GateDenied $event, string $action, string $outcome): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            $action,
            $outcome,
            reasonCode: $event->reasonCode,
            targetType: 'access_event',
            targetId: $event->accessEventId,
            metadata: [
                'event_id' => $event->eventId,
                'zone_id' => $event->zoneId,
                'lane_id' => $event->laneId,
                'direction' => $event->direction,
            ],
        );
    }
}
