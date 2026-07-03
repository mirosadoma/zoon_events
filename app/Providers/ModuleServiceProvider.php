<?php

namespace App\Providers;

use App\Modules\AdminConsole\Providers\AdminConsoleServiceProvider;
use App\Modules\Audit\Providers\AuditServiceProvider;
use App\Modules\Authorization\Providers\AuthorizationServiceProvider;
use App\Modules\FeatureFlags\Providers\FeatureFlagsServiceProvider;
use App\Modules\Identity\Providers\IdentityServiceProvider;
use App\Modules\Integrations\Providers\IntegrationServiceProvider;
use App\Modules\Operations\Providers\OperationsServiceProvider;
use App\Modules\Shared\Providers\SharedServiceProvider;
use App\Modules\Tenancy\Providers\TenancyServiceProvider;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * @var list<class-string<ServiceProvider>>
     */
    private array $moduleProviders = [
        SharedServiceProvider::class,
        IdentityServiceProvider::class,
        TenancyServiceProvider::class,
        AuthorizationServiceProvider::class,
        AuditServiceProvider::class,
        FeatureFlagsServiceProvider::class,
        OperationsServiceProvider::class,
        IntegrationServiceProvider::class,
        AdminConsoleServiceProvider::class,
    ];

    public function register(): void
    {
        foreach ($this->moduleProviders as $provider) {
            $this->app->register($provider);
        }
    }
}
