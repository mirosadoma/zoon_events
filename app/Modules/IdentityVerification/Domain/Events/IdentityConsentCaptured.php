<?php

namespace App\Modules\IdentityVerification\Domain\Events;

final readonly class IdentityConsentCaptured
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $attendeeId,
        public string $consentId,
    ) {}
}
