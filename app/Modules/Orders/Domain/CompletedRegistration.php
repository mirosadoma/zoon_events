<?php

namespace App\Modules\Orders\Domain;

use Carbon\CarbonImmutable;

final readonly class CompletedRegistration
{
    public function __construct(
        public string $orderId,
        public string $publicReference,
        public ?string $accessToken,
        public ?string $credentialId,
        public ?string $credentialToken,
        public ?CarbonImmutable $credentialExpiresAt,
        public bool $replayed,
    ) {}
}
