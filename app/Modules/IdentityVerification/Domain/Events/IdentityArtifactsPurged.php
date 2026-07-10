<?php

namespace App\Modules\IdentityVerification\Domain\Events;

final readonly class IdentityArtifactsPurged
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $verificationId,
        public int $artifactCount,
    ) {}
}
