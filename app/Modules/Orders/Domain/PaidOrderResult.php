<?php

namespace App\Modules\Orders\Domain;

final readonly class PaidOrderResult
{
    public function __construct(
        public string $orderId,
        public string $status,
        public ?string $credentialId,
        public ?string $credentialToken,
    ) {}
}
