<?php

namespace App\Modules\Audit\Application\Listeners\Phase4;

use App\Modules\AccessControl\Domain\Events\EmergencyCleared;
use App\Modules\AccessControl\Domain\Events\EmergencyRaised;
use App\Modules\Audit\Contracts\AuditWriter;

final readonly class EmergencyAuditListener
{
    public function __construct(private AuditWriter $audit) {}

    public function handleRaised(EmergencyRaised $event): void
    {
        $this->write($event, 'acs_emergency.raised');
    }

    public function handleCleared(EmergencyCleared $event): void
    {
        $this->write($event, 'acs_emergency.cleared');
    }

    private function write(EmergencyRaised|EmergencyCleared $event, string $action): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            $action,
            'succeeded',
            targetType: 'emergency_event',
            targetId: $event->emergencyEventId,
            metadata: [
                'event_id' => $event->eventId,
                'zone_id' => $event->zoneId,
            ],
        );
    }
}
