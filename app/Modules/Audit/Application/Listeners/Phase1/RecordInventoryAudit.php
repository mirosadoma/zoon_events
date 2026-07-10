<?php

namespace App\Modules\Audit\Application\Listeners\Phase1;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Ticketing\Domain\Events\InventoryStateChanged;

final readonly class RecordInventoryAudit
{
    public function __construct(
        private AuditWriter $audit,
        private Phase1AuditMapping $mapping,
    ) {}

    public function handle(InventoryStateChanged $event): void
    {
        $mapped = $this->mapping->for($event);
        $this->audit->write(
            'tenant',
            $event->tenantId,
            $mapped['action'],
            'succeeded',
            targetType: $mapped['target_type'],
            targetId: $mapped['target_id'],
            metadata: $mapped['metadata'],
        );
    }
}
