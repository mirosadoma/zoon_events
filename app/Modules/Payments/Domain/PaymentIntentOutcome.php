<?php

namespace App\Modules\Payments\Domain;

final readonly class PaymentIntentOutcome
{
    /** @param array<string,mixed>|null $action */
    public function __construct(
        public string $attemptId,
        public string $status,
        public ?array $action,
        public ?string $reasonCode,
    ) {}
}
