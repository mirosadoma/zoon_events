<?php

namespace App\Modules\Tenancy\Providers;

use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRoleAssignment;
use App\Modules\Tenancy\Application\Contracts\OrganizationEligibility;
use App\Modules\Tenancy\Application\Services\DatabaseOrganizationEligibility;
use App\Modules\Tenancy\Contracts\TenantContextResolver;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\Tenancy\Http\Bindings\TenantScopedBinding;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContextStore::class);
        $this->app->alias(TenantContextStore::class, TenantContextResolver::class);
        $this->app->bind(OrganizationEligibility::class, DatabaseOrganizationEligibility::class);
    }

    public function boot(): void
    {
        TenantScopedBinding::register('membership_id', TenantMembership::class);
        TenantScopedBinding::register('role_id', TenantRole::class);
        TenantScopedBinding::register('assignment_id', TenantRoleAssignment::class);
    }
}
