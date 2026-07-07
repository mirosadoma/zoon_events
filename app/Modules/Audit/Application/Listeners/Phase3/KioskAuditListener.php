<?php

namespace App\Modules\Audit\Application\Listeners\Phase3;

use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\Kiosk\Domain\Events\KioskPaired;
use App\Modules\Kiosk\Domain\Events\KioskRetired;
use App\Modules\Kiosk\Domain\Events\KioskStatusChanged;

final readonly class KioskAuditListener
{
    public function __construct(private AuditWriter $audit) {}

    public function handlePaired(KioskPaired $event): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            'kiosk.paired',
            'succeeded',
            targetType: 'kiosk',
            targetId: $event->kioskId,
            metadata: ['event_id' => $event->eventId, 'session_id' => $event->sessionId],
        );
    }

    public function handleRetired(KioskRetired $event): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            'kiosk.retired',
            'succeeded',
            targetType: 'kiosk',
            targetId: $event->kioskId,
            metadata: ['event_id' => $event->eventId],
        );
    }

    public function handleStatusChanged(KioskStatusChanged $event): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            'kiosk.status_changed',
            'succeeded',
            targetType: 'kiosk',
            targetId: $event->kioskId,
            metadata: ['event_id' => $event->eventId, 'old_status' => $event->oldStatus, 'new_status' => $event->newStatus],
        );
    }
}
