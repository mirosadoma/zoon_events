<?php

namespace App\Modules\IdentityVerification\Domain\Events;

final readonly class IdentityFaceCaptureSubmitted
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $attendeeId,
        public string $verificationId,
        public string $artifactId,
    ) {}
}
