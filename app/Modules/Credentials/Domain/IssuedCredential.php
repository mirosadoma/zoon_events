<?php

namespace App\Modules\Credentials\Domain;

use Carbon\CarbonImmutable;

final readonly class IssuedCredential
{
    public function __construct(
        public string $id,
        public string $token,
        public CarbonImmutable $expiresAt,
    ) {}
}
