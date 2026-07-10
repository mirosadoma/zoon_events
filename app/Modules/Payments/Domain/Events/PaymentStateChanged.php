<?php

namespace App\Modules\Payments\Domain\Events;

final readonly class PaymentStateChanged
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $attemptId,
        public string $status,
        public ?string $reasonCode,
    ) {}
}
