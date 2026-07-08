<?php

namespace App\Modules\IdentityVerification\Domain\Results;

final readonly class GovernmentIdentityVerificationResult
{
    public function __construct(
        public string $status,
        public ?string $reference = null,
        public ?GovernmentIdentityAttributes $attributes = null,
        public ?string $reasonCode = null,
    ) {}
}
