<?php

namespace App\Modules\Shared\Domain\Context;

use Illuminate\Support\Str;

final readonly class RequestId
{
    private function __construct(
        public string $value,
    ) {}

    public static function generate(): self
    {
        return new self(substr((string) Str::uuid(), 0, 64));
    }
}
