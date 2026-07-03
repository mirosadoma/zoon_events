<?php

namespace App\Modules\Shared\Domain\Context;

use Illuminate\Support\Str;

final readonly class CorrelationId
{
    private const PATTERN = '/^[A-Za-z0-9._:-]{1,64}$/';

    private function __construct(
        public string $value,
    ) {}

    public static function fromHeader(?string $value): self
    {
        if ($value === null || $value === '') {
            return self::generate();
        }

        if (! preg_match(self::PATTERN, $value)) {
            return self::generate();
        }

        return new self($value);
    }

    public static function generate(): self
    {
        return new self(substr((string) Str::ulid(), 0, 64));
    }
}
