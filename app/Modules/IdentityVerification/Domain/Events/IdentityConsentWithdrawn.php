<?php

namespace App\Modules\IdentityVerification\Domain\Events;

final readonly class IdentityConsentWithdrawn
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $attendeeId,
        public string $consentId,
    ) {}
}
