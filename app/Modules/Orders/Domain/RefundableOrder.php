<?php

namespace App\Modules\Orders\Domain;

final readonly class RefundableOrder
{
    public function __construct(
        public string $id,
        public int $totalMinor,
        public string $currency,
        public string $status,
    ) {}
}
