<?php

namespace App\Modules\Scanning\Application\Results;

use App\Modules\Scanning\Domain\Results\ScanDecision;

final readonly class ScanSubmission
{
    public function __construct(
        public string $scanEventId,
        public ScanDecision $decision,
        public ?string $attendeeDisplayName = null,
        public ?string $ticketTypeLabel = null,
    ) {}
}
