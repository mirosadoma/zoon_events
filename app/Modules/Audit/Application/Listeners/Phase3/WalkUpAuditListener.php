<?php

namespace App\Modules\Audit\Application\Listeners\Phase3;

use App\Modules\Attendees\Domain\Events\WalkUpAttendeeRegistered;
use App\Modules\Audit\Contracts\AuditWriter;

final readonly class WalkUpAuditListener
{
    public function __construct(private AuditWriter $audit) {}

    public function handleRegistered(WalkUpAttendeeRegistered $event): void
    {
        $this->audit->write(
            'tenant',
            $event->tenantId,
            'attendee.walk_up_registered',
            'succeeded',
            targetType: 'attendee',
            targetId: $event->attendeeId,
            metadata: ['event_id' => $event->eventId],
        );
    }
}
