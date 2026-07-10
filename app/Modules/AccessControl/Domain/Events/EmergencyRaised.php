<?php

namespace App\Modules\AccessControl\Domain\Events;

final readonly class EmergencyRaised
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $emergencyEventId,
        public ?string $zoneId,
    ) {}
}
