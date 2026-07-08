<?php

namespace App\Modules\IdentityVerification\Domain\Results;

final readonly class FaceLivenessResult
{
    public function __construct(
        public string $status,
        public ?string $reasonCode = null,
    ) {}
}
