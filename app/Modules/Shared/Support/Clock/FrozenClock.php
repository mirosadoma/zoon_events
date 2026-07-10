<?php

namespace App\Modules\Shared\Support\Clock;

use App\Modules\Shared\Contracts\Clock;
use Carbon\CarbonImmutable;

class FrozenClock implements Clock
{
    public function __construct(
        private readonly CarbonImmutable $frozenAt,
    ) {}

    public function now(): CarbonImmutable
    {
        return $this->frozenAt;
    }
}
