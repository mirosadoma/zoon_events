<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Database\Seeders\Concerns\SyncsRolePermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class FoundationSeeder extends Seeder
{
    use SyncsRolePermissions;

    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        if (app()->environment('production')) {
            return;
        }

        DB::transaction(function (): void {
            $admin = $this->seedPlatformAdmin();
            $this->seedPlatformRoles($admin);
            $this->assignPlatformRole($admin, 'Super Administrator', $admin);

            $tenant = $this->seedTenant($admin);
            $organizer = $this->seedOrganizer($admin);
            $this->seedTenantRoles($tenant, $admin);
            $this->seedTenantMembership($tenant, $organizer, $admin);
        });
    }

    private function seedPlatformAdmin(): User
    {
        $email = (string) config('zonetec.bootstrap_admin_email', DemoAccounts::ADMIN_EMAIL);
        $password = (string) config('zonetec.bootstrap_admin_password', DemoAccounts::ADMIN_PASSWORD);

        return User::query()->updateOrCreate(
            ['email' => mb_strtolower($email)],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make($password),
                'status' => LifecycleStatus::Active->value,
                'preferred_locale' => 'en',
            ],
        );
    }

    private function seedPlatformRoles(User $grantor): void
    {
        $roles = [
            'Super Administrator' => ['*'],
        ];

        foreach ($roles as $name => $permissionKeys) {
            $role = PlatformRole::query()->updateOrCreate(
                ['name' => $name],
                ['description' => "{$name} system role.", 'is_system' => true, 'created_by_user_id' => $grantor->id],
            );
            $this->syncPlatformRolePermissions($role, $permissionKeys, $grantor);
        }
    }

    private function seedTenant(User $grantor): Tenant
    {
        return Tenant::query()->updateOrCreate(
            ['slug' => DemoAccounts::TENANT_SLUG],
            [
                'name' => DemoAccounts::TENANT_ORG_NAME,
                'organization_type' => 'organizer',
                'default_locale' => 'ar',
                'timezone' => 'Asia/Riyadh',
                'data_residency_region' => 'sa',
                'status' => LifecycleStatus::Active->value,
                'created_by_user_id' => $grantor->id,
            ],
        );
    }

    private function seedOrganizer(User $grantor): User
    {
        return User::query()->updateOrCreate(
            ['email' => DemoAccounts::TENANT_EMAIL],
            [
                'name' => 'Organizer Admin',
                'password' => Hash::make(DemoAccounts::TENANT_PASSWORD),
                'status' => LifecycleStatus::Active->value,
                'preferred_locale' => 'ar',
                'created_by_user_id' => $grantor->id,
            ],
        );
    }

    private function seedTenantRoles(Tenant $tenant, User $grantor): void
    {
        $roles = [
            'Tenant Administrator' => ['*'],
            'Event Manager' => [
                'tenant.view', 'membership.view', 'role.view', 'audit.view', 'configuration.view',
                'event.view', 'event.manage', 'event.publish', 'event.cancel', 'event.reopen', 'event.archive',
                'category.view', 'category.manage',
                'registration.manage', 'ticketing.manage', 'order.view', 'order.manage',
                'attendee.view', 'attendee.manage', 'credential.view', 'credential.revoke', 'credential.reissue',
                'identity.configure', 'identity.review', 'identity.data.view',
                'wallet.pass.view', 'wallet.pass.generate', 'wallet.pass.manage',
                'checkin.scan.submit', 'checkin.dashboard.view', 'checkin.desk.perform',
                'kiosk.manage', 'kiosk.health.view',
                'badge.print', 'badge.reprint', 'badge.template.manage', 'attendee.walkup.register',
                'acs.configure', 'acs.events.view', 'acs.health.view',
                'marketplace.manage',
            ],
        ];

        foreach ($roles as $name => $permissionKeys) {
            $role = TenantRole::query()->withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name],
                ['description' => "{$name} system role.", 'is_system' => true, 'created_by_user_id' => $grantor->id],
            );
            $this->syncTenantRolePermissions($tenant, $role, $permissionKeys, $grantor);
        }
    }

    private function seedTenantMembership(Tenant $tenant, User $organizer, User $grantor): void
    {
        $membership = TenantMembership::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $organizer->id],
            ['status' => LifecycleStatus::Active->value, 'created_by_user_id' => $grantor->id],
        );

        $this->assignTenantRole($tenant, $membership, 'Tenant Administrator', $grantor);
    }

    private function assignPlatformRole(?User $user, string $roleName, User $grantor): void
    {
        if (! $user) {
            return;
        }
        $role = PlatformRole::query()->where('name', $roleName)->first();
        if (! $role) {
            return;
        }

        DB::table('platform_role_assignments')->updateOrInsert(
            ['user_id' => $user->id, 'platform_role_id' => $role->id, 'revoked_at' => null],
            ['granted_by_user_id' => $grantor->id, 'expires_at' => null, 'revoked_by_user_id' => null, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    private function assignTenantRole(Tenant $tenant, TenantMembership $membership, string $roleName, User $grantor): void
    {
        $role = TenantRole::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', $roleName)->first();
        if (! $role) {
            return;
        }

        DB::table('tenant_role_assignments')->updateOrInsert(
            ['tenant_id' => $tenant->id, 'tenant_membership_id' => $membership->id, 'tenant_role_id' => $role->id, 'revoked_at' => null],
            ['granted_by_user_id' => $grantor->id, 'expires_at' => null, 'revoked_by_user_id' => null, 'created_at' => now(), 'updated_at' => now()],
        );
    }
}
