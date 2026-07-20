<?php

namespace App\Modules\VenueMarketplace\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class Money
{
    public function __construct(
        public int $minor,
        public string $currency,
    ) {
        if ($minor < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Currency must be an uppercase ISO 4217 code.');
        }
    }

    public function add(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currencies must match.');
        }

        if ($other->minor > PHP_INT_MAX - $this->minor) {
            throw new InvalidArgumentException('Money amount overflow.');
        }

        return new self($this->minor + $other->minor, $this->currency);
    }
}
