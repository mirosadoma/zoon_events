<?php

namespace App\Modules\Ticketing\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class Money
{
    public function __construct(
        public int $minor,
        public string $currency,
    ) {
        if ($minor < 0 || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Money value is invalid.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }
}
