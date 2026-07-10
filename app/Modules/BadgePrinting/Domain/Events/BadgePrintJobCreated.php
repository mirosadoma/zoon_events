<?php

namespace App\Modules\BadgePrinting\Domain\Events;

final readonly class BadgePrintJobCreated
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $badgePrintJobId,
        public string $attendeeId,
        public ?string $reasonCode,
    ) {}
}
