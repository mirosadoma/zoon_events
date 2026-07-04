<?php

namespace App\Modules\Audit\Application\Listeners\Phase1;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Notifications\Domain\Events\NotificationTerminalStateReached;

final readonly class RecordNotificationAudit
{
    public function __construct(private AuditWriter $audit, private Phase1AuditMapping $mapping) {}

    public function handle(NotificationTerminalStateReached $event): void
    {
        $mapped = $this->mapping->for($event);
        $this->audit->write(
            'tenant',
            $event->tenantId,
            $mapped['action'],
            $event->status === 'permanent_failure' ? 'failed' : 'succeeded',
            reasonCode: $event->reasonCode,
            targetType: $mapped['target_type'],
            targetId: $mapped['target_id'],
            metadata: $mapped['metadata'],
        );
    }
}
