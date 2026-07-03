<?php

namespace App\Modules\Shared\Providers;

use App\Modules\Shared\Contracts\Clock;
use App\Modules\Shared\Domain\Context\RequestContextStore;
use App\Modules\Shared\Support\Clock\SystemClock;
use Illuminate\Support\ServiceProvider;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(RequestContextStore::class);
    }
}
