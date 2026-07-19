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

        DB::transaction(function (): void {
            $admin = $this->seedPlatformAdmin();
            $this->seedPlatformRoles($admin);
            $this->assignPlatformRole($admin, 'Super Administrator', $admin);

            $tenant = $this->seedTenant($admin);
            $this->seedTenantRoles($tenant, $admin);

            $organizer = $this->seedStaffUser(
                DemoAccounts::TENANT_EMAIL,
                DemoAccounts::TENANT_PASSWORD,
                'Organizer Admin',
                'ar',
                $admin,
            );
            $ticketing = $this->seedStaffUser(
                DemoAccounts::TICKETING_EMAIL,
                DemoAccounts::TICKETING_PASSWORD,
                'Ticketing Manager',
                'en',
                $admin,
            );
            $onsite = $this->seedStaffUser(
                DemoAccounts::ONSITE_EMAIL,
                DemoAccounts::ONSITE_PASSWORD,
                'On-Site Staff',
                'en',
                $admin,
            );
            $acs = $this->seedStaffUser(
                DemoAccounts::ACS_EMAIL,
                DemoAccounts::ACS_PASSWORD,
                'ACS Operator',
                'en',
                $admin,
            );

            // Super Admin also gets tenant admin membership so /tenant/* works in demos.
            $this->seedTenantMembership($tenant, $admin, 'Tenant Administrator', $admin);
            $this->seedTenantMembership($tenant, $organizer, 'Tenant Administrator', $admin);
            $this->seedTenantMembership($tenant, $ticketing, 'Ticketing Manager', $admin);
            $this->seedTenantMembership($tenant, $onsite, 'On-Site Staff', $admin);
            $this->seedTenantMembership($tenant, $acs, 'ACS Operator', $admin);
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
                'type' => 'staff',
                'status' => LifecycleStatus::Active->value,
                'preferred_locale' => 'en',
            ],
        );
    }

    private function seedStaffUser(string $email, string $password, string $name, string $locale, User $grantor): User
    {
        return User::query()->updateOrCreate(
            ['email' => mb_strtolower($email)],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'type' => 'staff',
                'status' => LifecycleStatus::Active->value,
                'preferred_locale' => $locale,
                'created_by_user_id' => $grantor->id,
            ],
        );
    }

    private function seedPlatformRoles(User $grantor): void
    {
        $roles = [
            'Super Administrator' => ['*'],
            'Security Auditor' => [
                'platform.audit.view',
                'platform.audit.export',
                'platform.audit.verify',
            ],
            'Operations Viewer' => [
                'operations.health.view',
                'platform.configuration.view',
            ],
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

    private function seedTenantRoles(Tenant $tenant, User $grantor): void
    {
        $roles = [
            'Tenant Administrator' => ['*'],
            'Event Manager' => [
                'tenant.view', 'membership.view', 'role.view', 'audit.view', 'configuration.view',
                'event.view', 'event.manage', 'event.publish', 'event.cancel', 'event.reopen', 'event.archive',
                'event.invite.view', 'event.invite.manage',
                'privilege.view', 'privilege.manage',
                'category.view', 'category.manage',
                'registration.manage', 'ticketing.manage', 'order.view', 'order.manage',
                'attendee.view', 'attendee.manage', 'credential.view', 'credential.validate', 'credential.revoke', 'credential.reissue',
                'identity.configure', 'identity.review', 'identity.data.view',
                'wallet.pass.view', 'wallet.pass.generate', 'wallet.pass.manage',
                'checkin.scan.submit', 'checkin.dashboard.view', 'checkin.desk.perform',
                'kiosk.manage', 'kiosk.health.view',
                'badge.print', 'badge.reprint', 'badge.template.manage', 'attendee.walkup.register',
                'acs.configure', 'acs.events.view', 'acs.health.view',
                'marketplace.manage',
                'subscription.view',
            ],
            'Ticketing Manager' => [
                'event.view', 'ticketing.manage', 'order.view', 'order.manage', 'payment.refund',
                'attendee.view', 'credential.view', 'credential.revoke', 'credential.reissue',
                'wallet.pass.view', 'wallet.pass.generate',
            ],
            'On-Site Staff' => [
                'event.view', 'attendee.view', 'credential.view',
                'checkin.scan.submit', 'checkin.scan.override', 'checkin.dashboard.view', 'checkin.desk.perform',
                'kiosk.health.view', 'badge.print', 'badge.reprint', 'attendee.walkup.register',
            ],
            'ACS Operator' => [
                'event.view', 'acs.configure', 'acs.events.view', 'acs.health.view', 'acs.emergency.manage',
            ],
            'Venue Owner Admin' => [
                'venue.manage', 'rentals.approve', 'reports.view', 'audit.view',
            ],
            'Venue Asset Manager' => [
                'venue.manage',
            ],
            'Venue Rental Approver' => [
                'rentals.approve',
            ],
            'Venue Finance Manager' => [
                'reports.view', 'audit.view',
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

    private function seedTenantMembership(Tenant $tenant, User $user, string $roleName, User $grantor): void
    {
        $membership = TenantMembership::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $user->id],
            ['status' => LifecycleStatus::Active->value, 'created_by_user_id' => $grantor->id],
        );

        $this->assignTenantRole($tenant, $membership, $roleName, $grantor);
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
