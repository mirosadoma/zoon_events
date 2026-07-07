<?php

namespace App\Modules\Attendees\Domain\Events;

final readonly class WalkUpAttendeeRegistered
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $attendeeId,
    ) {}
}
