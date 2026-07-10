<?php

namespace App\Modules\Payments\Domain;

final readonly class PaymentContext
{
    public function __construct(
        public string $tenantId,
        public string $accountId,
        public string $correlationId,
        public string $idempotencyKey,
        public bool $live,
        public int $timeoutMs,
    ) {}
}
