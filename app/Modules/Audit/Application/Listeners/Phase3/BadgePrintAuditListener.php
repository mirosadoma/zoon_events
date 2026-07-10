<?php

namespace App\Modules\Audit\Application\Listeners\Phase3;

use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\BadgePrinting\Domain\Events\BadgePrintJobCreated;
use App\Modules\BadgePrinting\Domain\Events\BadgePrintJobFailed;
use App\Modules\BadgePrinting\Domain\Events\BadgePrintJobPrinted;
use App\Modules\BadgePrinting\Domain\Events\BadgePrintJobReprinted;

final readonly class BadgePrintAuditListener
{
    public function __construct(private AuditWriter $audit) {}

    public function handleCreated(BadgePrintJobCreated $event): void
    {
        $this->write('badge_print.created', $event);
    }

    public function handlePrinted(BadgePrintJobPrinted $event): void
    {
        $this->write('badge_print.printed', $event, 'succeeded');
    }

    public function handleFailed(BadgePrintJobFailed $event): void
    {
        $this->write('badge_print.failed', $event, 'failed');
    }

    public function handleReprinted(BadgePrintJobReprinted $event): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            'badge_print.reprinted',
            'succeeded',
            targetType: 'badge_print_job',
            targetId: $event->badgePrintJobId,
            metadata: [
                'event_id' => $event->eventId,
                'attendee_id' => $event->attendeeId,
                'original_print_job_id' => $event->originalPrintJobId,
                'reason' => $event->reason,
            ],
        );
    }

    private function write(
        string $action,
        BadgePrintJobCreated|BadgePrintJobPrinted|BadgePrintJobFailed $event,
        string $outcome = 'succeeded',
    ): void {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            $action,
            $outcome,
            reasonCode: $event->reasonCode,
            targetType: 'badge_print_job',
            targetId: $event->badgePrintJobId,
            metadata: ['event_id' => $event->eventId, 'attendee_id' => $event->attendeeId],
        );
    }
}
