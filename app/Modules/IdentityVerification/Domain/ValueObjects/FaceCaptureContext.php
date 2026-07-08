<?php

namespace App\Modules\IdentityVerification\Domain\ValueObjects;

final readonly class FaceCaptureContext
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $attendeeId,
        public string $idempotencyKey,
    ) {}
}
