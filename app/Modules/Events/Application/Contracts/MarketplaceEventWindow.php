<?php

namespace App\Modules\Events\Application\Contracts;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class MarketplaceEventWindow
{
    public function __construct(
        public string $timezone,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {
        if ($endsAt <= $startsAt) {
            throw new InvalidArgumentException('Marketplace event window is invalid.');
        }
    }
}
