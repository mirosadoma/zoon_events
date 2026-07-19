<?php

namespace Database\Seeders\Concerns;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\Permission;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Support\Facades\DB;

trait SyncsRolePermissions
{
    /** @param list<string> $permissionKeys */
    protected function syncPlatformRolePermissions(PlatformRole $role, array $permissionKeys, User $grantor): void
    {
        $permissionIds = $permissionKeys === ['*']
            ? Permission::query()->where('scope', 'platform')->pluck('id')->all()
            : Permission::query()->where('scope', 'platform')->whereIn('key', $permissionKeys)->pluck('id')->all();

        DB::table('platform_role_permissions')->where('platform_role_id', $role->id)->delete();

        foreach ($permissionIds as $permissionId) {
            DB::table('platform_role_permissions')->insert([
                'platform_role_id' => $role->id,
                'permission_id' => $permissionId,
                'granted_by_user_id' => $grantor->id,
                'created_at' => now(),
            ]);
        }
    }

    /** @param list<string> $permissionKeys */
    protected function syncTenantRolePermissions(Tenant $tenant, TenantRole $role, array $permissionKeys, User $grantor): void
    {
        $permissionIds = $permissionKeys === ['*']
            ? Permission::query()->where('scope', 'tenant')->pluck('id')->all()
            : Permission::query()->where('scope', 'tenant')->whereIn('key', $permissionKeys)->pluck('id')->all();

        DB::table('tenant_role_permissions')->where('tenant_role_id', $role->id)->delete();

        foreach ($permissionIds as $permissionId) {
            DB::table('tenant_role_permissions')->insert([
                'tenant_id' => $tenant->id,
                'tenant_role_id' => $role->id,
                'permission_id' => $permissionId,
                'granted_by_user_id' => $grantor->id,
                'created_at' => now(),
            ]);
        }
    }
}
