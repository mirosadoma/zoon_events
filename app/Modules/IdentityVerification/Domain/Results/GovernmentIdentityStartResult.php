<?php

namespace App\Modules\IdentityVerification\Domain\Results;

final readonly class GovernmentIdentityStartResult
{
    public function __construct(
        public string $status,
        public ?string $reference = null,
        public ?string $reasonCode = null,
        public ?string $redirectUrl = null,
    ) {}
}
