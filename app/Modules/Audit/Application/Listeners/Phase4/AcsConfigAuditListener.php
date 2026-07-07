<?php

namespace App\Modules\Audit\Application\Listeners\Phase4;

use App\Modules\AccessControl\Domain\Events\AcsIntegrationCredentialRegistered;
use App\Modules\AccessControl\Domain\Events\AcsLaneCreated;
use App\Modules\AccessControl\Domain\Events\AcsRuleCreated;
use App\Modules\AccessControl\Domain\Events\AcsZoneCreated;
use App\Modules\AccessControl\Domain\Events\AcsZoneUpdated;
use App\Modules\Audit\Contracts\AuditWriter;

final readonly class AcsConfigAuditListener
{
    public function __construct(private AuditWriter $audit) {}

    public function handleZoneCreated(AcsZoneCreated $event): void
    {
        $this->write($event->tenantId, 'acs_zone.created', $event->zoneId, $event->eventId);
    }

    public function handleZoneUpdated(AcsZoneUpdated $event): void
    {
        $this->write($event->tenantId, 'acs_zone.updated', $event->zoneId, $event->eventId);
    }

    public function handleLaneCreated(AcsLaneCreated $event): void
    {
        $this->write($event->tenantId, 'acs_lane.created', $event->laneId, $event->eventId);
    }

    public function handleRuleCreated(AcsRuleCreated $event): void
    {
        $this->write($event->tenantId, 'acs_rule.created', $event->ruleId, $event->eventId);
    }

    public function handleCredentialRegistered(AcsIntegrationCredentialRegistered $event): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            'acs_integration.credential_registered',
            'succeeded',
            targetType: 'acs_integration_credential',
            targetId: $event->credentialId,
            metadata: ['event_id' => $event->eventId],
        );
    }

    private function write(string $tenantId, string $action, string $targetId, string $eventId): void
    {
        $targetType = match (true) {
            str_starts_with($action, 'acs_zone') => 'acs_zone',
            str_starts_with($action, 'acs_lane') => 'acs_lane',
            default => 'acs_rule',
        };

        $this->audit->write(
            'tenant',
            $tenantId,
            $action,
            'succeeded',
            targetType: $targetType,
            targetId: $targetId,
            metadata: ['event_id' => $eventId],
        );
    }
}
