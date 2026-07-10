<?php

namespace App\Modules\Identity\Providers;

use App\Modules\Identity\Application\NullAuthenticators;
use App\Modules\Identity\Contracts\ApiKeyAuthenticator;
use App\Modules\Identity\Contracts\MfaAuthenticator;
use App\Modules\Identity\Contracts\ServiceTokenAuthenticator;
use App\Modules\Identity\Support\AuthenticationRateLimiter;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NullAuthenticators::class);
        $this->app->alias(NullAuthenticators::class, MfaAuthenticator::class);
        $this->app->alias(NullAuthenticators::class, ApiKeyAuthenticator::class);
        $this->app->alias(NullAuthenticators::class, ServiceTokenAuthenticator::class);
    }

    public function boot(): void
    {
        AuthenticationRateLimiter::register();
    }
}
