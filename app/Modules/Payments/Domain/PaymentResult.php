<?php

namespace App\Modules\Payments\Domain;

final readonly class PaymentResult
{
    /** @param array<string,mixed>|null $action */
    public function __construct(
        public PaymentStatus $status,
        public ?string $providerPaymentId,
        public int $capturedMinor,
        public int $refundedMinor,
        public string $currency,
        public ?string $reasonCode = null,
        public ?array $action = null,
    ) {}
}
