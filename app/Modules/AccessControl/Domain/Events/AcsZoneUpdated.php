<?php

namespace App\Modules\AccessControl\Domain\Events;

final readonly class AcsZoneUpdated
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $zoneId,
    ) {}
}
