<?php

namespace App\Modules\VenueMarketplace\Domain\ValueObjects;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Stringable;

final readonly class OpaqueMarketplaceId implements Stringable
{
    public function __construct(public string $value)
    {
        if (! Str::isUlid($value)) {
            throw new InvalidArgumentException('Marketplace identifiers must be ULIDs.');
        }
    }

    public static function generate(): self
    {
        return new self((string) Str::ulid());
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
