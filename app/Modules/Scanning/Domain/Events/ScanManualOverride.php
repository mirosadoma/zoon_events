<?php

namespace App\Modules\Scanning\Domain\Events;

final readonly class ScanManualOverride
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $scanEventId,
        public ?string $credentialId,
        public string $reasonCode,
        public ?string $overrideReason,
    ) {}
}
