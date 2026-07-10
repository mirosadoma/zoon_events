<?php

namespace App\Modules\IdentityVerification\Domain\Results;

final readonly class FaceCaptureResult
{
    public function __construct(
        public string $status,
        public ?string $reference = null,
        public ?string $artifactType = null,
        public ?FaceLivenessResult $liveness = null,
        public ?string $reasonCode = null,
    ) {}
}
