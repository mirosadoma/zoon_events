<?php

namespace App\Modules\Ticketing\Domain\ValueObjects;

use Carbon\CarbonImmutable;

final readonly class PriceQuote
{
    public function __construct(
        public Money $money,
        public ?string $priceTierId,
        public CarbonImmutable $quotedAt,
    ) {}
}
