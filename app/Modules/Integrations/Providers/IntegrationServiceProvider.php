<?php

namespace App\Modules\Integrations\Providers;

use App\Modules\Integrations\Application\AdapterRegistry;
use App\Modules\Integrations\Testing\FakeCapabilityAdapter;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdapterRegistry::class, function ($app): AdapterRegistry {
            if ($app->environment('production')) {
                return new AdapterRegistry([]);
            }

            $app->singleton(FakeCapabilityAdapter::class);

            return new AdapterRegistry([$app->make(FakeCapabilityAdapter::class)]);
        });
    }
}
