<?php

namespace App\Modules\AccessControl\Domain\Events;

final readonly class AccessEventIngested
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $accessEventId,
        public string $laneId,
        public string $zoneId,
        public ?string $credentialId,
        public string $direction,
    ) {}
}
