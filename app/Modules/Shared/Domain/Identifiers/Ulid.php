<?php

namespace App\Modules\Shared\Domain\Identifiers;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class Ulid
{
    private function __construct(
        public string $value,
    ) {}

    public static function generate(): self
    {
        return new self((string) Str::ulid());
    }

    public static function fromString(string $value): self
    {
        if (! self::isValid($value)) {
            throw new InvalidArgumentException('The given value is not a valid ULID.');
        }

        return new self($value);
    }

    public static function isValid(string $value): bool
    {
        return Str::isUlid($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
