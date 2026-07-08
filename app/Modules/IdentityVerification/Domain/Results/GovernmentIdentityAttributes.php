<?php

namespace App\Modules\IdentityVerification\Domain\Results;

final readonly class GovernmentIdentityAttributes
{
    public function __construct(
        public ?string $verifiedName = null,
        public ?string $verifiedNationality = null,
    ) {}
}
