<?php

namespace App\Modules\Scanning\Domain\ValueObjects;

final readonly class ScannerAppSessionContext
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $eventZoneId,
        public string $sessionId,
    ) {}
}
