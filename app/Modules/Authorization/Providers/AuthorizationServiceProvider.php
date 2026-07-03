<?php

namespace App\Modules\Authorization\Providers;

use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Authorization\Contracts\PermissionEvaluator as PermissionEvaluatorContract;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthorizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionEvaluator::class);
        $this->app->alias(PermissionEvaluator::class, PermissionEvaluatorContract::class);
    }

    public function boot(): void
    {
        foreach (PermissionSeeder::definitions() as $definition) {
            Gate::define($definition['key'], function ($user) use ($definition): bool {
                $evaluator = app(PermissionEvaluator::class);

                if ($definition['scope'] === 'platform') {
                    return $evaluator->hasPlatformPermission($user, $definition['key']);
                }

                $context = app(TenantContextStore::class)->currentOrNull();

                return $context !== null
                    && $context->actor->is($user)
                    && $evaluator->hasTenantPermission($context, $definition['key']);
            });
        }
    }
}
