<?php

namespace App\Modules\IdentityVerification\Domain\Events;

final readonly class IdentityRequirementConfigured
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $requirementId,
        public ?string $ticketTypeId = null,
    ) {}
}
