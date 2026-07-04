<?php

namespace App\Modules\Orders\Domain;

final readonly class PayableOrder
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $eventId,
        public string $publicReference,
        public int $totalMinor,
        public string $currency,
        public string $status,
    ) {}
}
