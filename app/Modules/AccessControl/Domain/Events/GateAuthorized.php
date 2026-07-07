<?php

namespace App\Modules\AccessControl\Domain\Events;

final readonly class GateAuthorized
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $accessEventId,
        public ?string $credentialId,
        public ?string $zoneId,
        public ?string $laneId,
        public string $direction,
        public string $reasonCode,
    ) {}
}
