<?php

namespace App\Modules\Kiosk\Domain\Events;

final readonly class KioskPaired
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $kioskId,
        public string $sessionId,
    ) {}
}
