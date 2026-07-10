<?php

namespace App\Modules\Shared\Support\Clock;

use App\Modules\Shared\Contracts\Clock;
use Carbon\CarbonImmutable;

class SystemClock implements Clock
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }
}
