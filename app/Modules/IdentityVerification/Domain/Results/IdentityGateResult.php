<?php

namespace App\Modules\IdentityVerification\Domain\Results;

final readonly class IdentityGateResult
{
    public function __construct(
        public bool $satisfied,
        public string $requirementLevel,
        public string $status,
        public ?string $reasonCode = null,
    ) {}
}
