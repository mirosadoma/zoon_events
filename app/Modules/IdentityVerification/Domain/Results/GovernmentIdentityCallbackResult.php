<?php

namespace App\Modules\IdentityVerification\Domain\Results;

final readonly class GovernmentIdentityCallbackResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $status,
        public ?string $reference = null,
        public ?string $reasonCode = null,
        public array $raw = [],
    ) {}
}
