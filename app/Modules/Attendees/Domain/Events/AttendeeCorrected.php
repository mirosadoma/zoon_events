<?php

namespace App\Modules\Attendees\Domain\Events;

final readonly class AttendeeCorrected
{
    /** @param list<string> $changedFields */
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $attendeeId,
        public array $changedFields,
    ) {}
}
