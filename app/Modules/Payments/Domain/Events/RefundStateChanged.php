<?php

namespace App\Modules\Payments\Domain\Events;

final readonly class RefundStateChanged
{
    public function __construct(
        public string $tenantId,
        public string $eventId,
        public string $refundId,
        public string $status,
    ) {}
}
