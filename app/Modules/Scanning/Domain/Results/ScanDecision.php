<?php

namespace App\Modules\Scanning\Domain\Results;

final readonly class ScanDecision
{
    public function __construct(
        public string $result,
        public string $reasonCode,
        public ?string $credentialId = null,
        public ?string $attendeeId = null,
    ) {}
}
