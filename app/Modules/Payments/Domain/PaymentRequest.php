<?php

namespace App\Modules\Payments\Domain;

final readonly class PaymentRequest
{
    public function __construct(
        public string $orderReference,
        public int $amountMinor,
        public string $currency,
        public string $returnUrl,
    ) {}
}
