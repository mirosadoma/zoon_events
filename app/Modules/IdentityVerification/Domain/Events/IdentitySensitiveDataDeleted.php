<?php

namespace App\Modules\IdentityVerification\Domain\Events;

final readonly class IdentitySensitiveDataDeleted
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $attendeeId,
        public string $verificationId,
        public ?string $actorId = null,
    ) {}
}
