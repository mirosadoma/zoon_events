<?php

namespace App\Modules\Kiosk\Domain\ValueObjects;

final readonly class KioskSessionContext
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $kioskId,
        public bool $confirmed,
    ) {}
}
