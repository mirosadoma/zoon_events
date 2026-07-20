<?php

namespace App\Modules\VenueMarketplace\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RentalWindow
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {
        if ($endsAt <= $startsAt) {
            throw new InvalidArgumentException('Rental end must be after start.');
        }
    }

    public function contains(DateTimeImmutable $instant): bool
    {
        return $this->startsAt <= $instant && $instant < $this->endsAt;
    }
}
