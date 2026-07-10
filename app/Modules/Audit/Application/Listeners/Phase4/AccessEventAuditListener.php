<?php

namespace App\Modules\Audit\Application\Listeners\Phase4;

use App\Modules\AccessControl\Domain\Events\AccessEventIngested;
use App\Modules\Audit\Contracts\AuditWriter;

final readonly class AccessEventAuditListener
{
    public function __construct(private AuditWriter $audit) {}

    public function handle(AccessEventIngested $event): void
    {
        $action = $event->direction === 'exit' ? 'access.exit' : 'access.entry';

        $this->audit->write(
            'tenant',
            $event->tenantId,
            $action,
            'succeeded',
            targetType: 'access_event',
            targetId: $event->accessEventId,
            metadata: [
                'event_id' => $event->eventId,
                'lane_id' => $event->laneId,
                'zone_id' => $event->zoneId,
                'direction' => $event->direction,
            ],
        );
    }
}
