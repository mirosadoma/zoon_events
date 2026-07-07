<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\Permission;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class SystemRoleSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::query()->orderBy('created_at')->first();

        if (! $creator instanceof User) {
            return;
        }

        DB::transaction(function () use ($creator): void {
            $definitions = [
                'Platform Administrator' => Permission::query()->where('scope', 'platform')->pluck('id')->all(),
                'Security Auditor' => Permission::query()->whereIn('key', ['platform.audit.view', 'platform.audit.export', 'platform.audit.verify'])->pluck('id')->all(),
                'Operations Viewer' => Permission::query()->whereIn('key', ['operations.health.view', 'platform.configuration.view'])->pluck('id')->all(),
            ];

            foreach ($definitions as $name => $permissionIds) {
                $role = PlatformRole::query()->updateOrCreate(
                    ['name' => $name],
                    ['description' => "{$name} system role.", 'is_system' => true, 'created_by_user_id' => $creator->id],
                );
                $role->permissions()->syncWithPivotValues($permissionIds, [
                    'granted_by_user_id' => $creator->id,
                    'created_at' => now(),
                ]);
            }

            $tenantPermissionIds = Permission::query()->where('scope', 'tenant')->pluck('id')->all();
            Tenant::query()->each(function (Tenant $tenant) use ($creator, $tenantPermissionIds): void {
                $roles = [
                    'Tenant Administrator' => $tenantPermissionIds,
                    'Event Manager' => Permission::query()->whereIn('key', [
                        'event.view', 'event.manage', 'event.publish', 'event.cancel', 'event.reopen', 'event.archive',
                        'registration.manage', 'ticketing.manage', 'order.view',
                        'attendee.view', 'attendee.manage', 'credential.view',
                        'credential.revoke', 'credential.reissue',
                    ])->pluck('id')->all(),
                    'Ticketing Manager' => Permission::query()->whereIn('key', [
                        'event.view', 'ticketing.manage', 'order.view', 'order.manage',
                        'payment.refund', 'attendee.view', 'credential.view',
                    ])->pluck('id')->all(),
                    'On-Site Staff' => Permission::query()->whereIn('key', [
                        'checkin.scan.submit', 'checkin.dashboard.view', 'checkin.desk.perform',
                    ])->pluck('id')->all(),
                ];

                foreach ($roles as $name => $permissionIds) {
                    $role = TenantRole::query()->withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'name' => $name],
                        ['description' => "{$name} system role.", 'is_system' => true, 'created_by_user_id' => $creator->id],
                    );
                    DB::table('tenant_role_permissions')->where('tenant_role_id', $role->id)->delete();
                    foreach ($permissionIds as $permissionId) {
                        DB::table('tenant_role_permissions')->insert([
                            'tenant_id' => $tenant->id,
                            'tenant_role_id' => $role->id,
                            'permission_id' => $permissionId,
                            'granted_by_user_id' => $creator->id,
                            'created_at' => now(),
                        ]);
                    }
                }
            });
        });
    }
}
