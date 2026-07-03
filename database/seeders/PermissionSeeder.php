<?php

namespace Database\Seeders;

use App\Modules\Authorization\Infrastructure\Persistence\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * @return array<int, array<string, string>>
     */
    public static function definitions(): array
    {
        return [
            ['key' => 'tenant.view', 'module' => 'tenancy', 'description' => 'View tenant foundation data.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'membership.view', 'module' => 'tenancy', 'description' => 'View tenant memberships.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'membership.manage', 'module' => 'tenancy', 'description' => 'Manage tenant memberships.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'role.view', 'module' => 'authorization', 'description' => 'View tenant roles.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'role.manage', 'module' => 'authorization', 'description' => 'Manage tenant roles.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'role.assign', 'module' => 'authorization', 'description' => 'Assign tenant roles.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'audit.view', 'module' => 'audit', 'description' => 'View tenant audit logs.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'audit.export', 'module' => 'audit', 'description' => 'Export tenant audit logs.', 'scope' => 'tenant', 'risk_level' => 'privileged'],
            ['key' => 'audit.verify', 'module' => 'audit', 'description' => 'Verify tenant audit integrity.', 'scope' => 'tenant', 'risk_level' => 'privileged'],
            ['key' => 'configuration.view', 'module' => 'tenancy', 'description' => 'View tenant configuration.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'feature_flag.view', 'module' => 'feature-flags', 'description' => 'View tenant feature flags.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'feature_flag.manage', 'module' => 'feature-flags', 'description' => 'Manage tenant feature flags.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'platform.tenant.view', 'module' => 'tenancy', 'description' => 'View platform tenants.', 'scope' => 'platform', 'risk_level' => 'standard'],
            ['key' => 'platform.tenant.manage', 'module' => 'tenancy', 'description' => 'Manage platform tenants.', 'scope' => 'platform', 'risk_level' => 'privileged'],
            ['key' => 'platform.user.view', 'module' => 'identity', 'description' => 'View platform users.', 'scope' => 'platform', 'risk_level' => 'standard'],
            ['key' => 'platform.user.manage', 'module' => 'identity', 'description' => 'Manage platform users.', 'scope' => 'platform', 'risk_level' => 'privileged'],
            ['key' => 'platform.role.view', 'module' => 'authorization', 'description' => 'View platform roles.', 'scope' => 'platform', 'risk_level' => 'standard'],
            ['key' => 'platform.role.manage', 'module' => 'authorization', 'description' => 'Manage platform roles.', 'scope' => 'platform', 'risk_level' => 'privileged'],
            ['key' => 'platform.role.assign', 'module' => 'authorization', 'description' => 'Assign platform roles.', 'scope' => 'platform', 'risk_level' => 'privileged'],
            ['key' => 'platform.access.recover', 'module' => 'authorization', 'description' => 'Perform platform recovery actions.', 'scope' => 'platform', 'risk_level' => 'privileged'],
            ['key' => 'platform.audit.view', 'module' => 'audit', 'description' => 'View platform audit logs.', 'scope' => 'platform', 'risk_level' => 'privileged'],
            ['key' => 'platform.audit.export', 'module' => 'audit', 'description' => 'Export platform audit logs.', 'scope' => 'platform', 'risk_level' => 'privileged'],
            ['key' => 'platform.audit.verify', 'module' => 'audit', 'description' => 'Verify platform audit integrity.', 'scope' => 'platform', 'risk_level' => 'privileged'],
            ['key' => 'operations.health.view', 'module' => 'operations', 'description' => 'View detailed platform health.', 'scope' => 'platform', 'risk_level' => 'standard'],
            ['key' => 'platform.feature_flag.view', 'module' => 'feature-flags', 'description' => 'View platform feature flags.', 'scope' => 'platform', 'risk_level' => 'standard'],
            ['key' => 'platform.feature_flag.manage', 'module' => 'feature-flags', 'description' => 'Manage platform feature flags.', 'scope' => 'platform', 'risk_level' => 'privileged'],
            ['key' => 'platform.configuration.view', 'module' => 'operations', 'description' => 'View platform configuration schemas.', 'scope' => 'platform', 'risk_level' => 'standard'],
        ];
    }

    public function run(): void
    {
        foreach (self::definitions() as $definition) {
            Permission::query()->updateOrCreate(
                ['key' => $definition['key']],
                $definition,
            );
        }
    }
}
