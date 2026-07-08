<?php

namespace App\Modules\Authorization\Application;

use App\Models\User;
use App\Modules\Authorization\Contracts\PermissionEvaluator as PermissionEvaluatorContract;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRoleAssignment;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRoleAssignment;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Tenancy\Infrastructure\Persistence\Scopes\TenantScope;
use Carbon\CarbonImmutable;

class PermissionEvaluator implements PermissionEvaluatorContract
{
    public function hasPlatformPermission(User $user, string $permission): bool
    {
        return PlatformRoleAssignment::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', CarbonImmutable::now());
            })
            ->whereHas('role.permissions', fn ($query) => $query
                ->where('key', $permission)
                ->where('scope', 'platform'))
            ->exists();
    }

    public function hasTenantPermission(TenantContext $context, string $permission): bool
    {
        return TenantRoleAssignment::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_membership_id', $context->membership->id)
            ->where('tenant_id', $context->tenant->id)
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', CarbonImmutable::now());
            })
            ->whereHas('role', fn ($query) => $query
                ->withoutGlobalScope(TenantScope::class)
                ->whereColumn('tenant_roles.tenant_id', 'tenant_role_assignments.tenant_id')
                ->whereHas('permissions', fn ($permissions) => $permissions
                    ->where('key', $permission)
                    ->where('scope', 'tenant')))
            ->exists();
    }
}
