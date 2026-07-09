<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\Permission;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Database\Seeders\Concerns\SyncsRolePermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds permissions, platform admin, demo tenants, workforce accounts, system roles, and assignments.
 */
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
            $grantor = $this->seedPlatformAdministrator();
            $this->seedFixtureTenants($grantor);
            $this->seedWorkforceAccounts($grantor);
            $this->seedSystemRoles($grantor);
            $this->seedRoleAssignments($grantor);
        });
    }

    private function seedPlatformAdministrator(): User
    {
        $email = (string) config('zonetec.bootstrap_admin_email', DemoAccounts::PLATFORM_ADMIN_EMAIL);
        $password = (string) config('zonetec.bootstrap_admin_password', 'admin1234');

        if ($email === '' || strlen($password) < 6) {
            throw new \RuntimeException('Bootstrap administrator email and a password of at least 6 characters are required.');
        }

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

    private function seedFixtureTenants(User $grantor): void
    {
        User::query()->updateOrCreate(
            ['email' => DemoAccounts::FIXTURE_CREATOR_EMAIL],
            [
                'name' => 'Fixture Creator',
                'password' => Hash::make(DemoAccounts::FIXTURE_CREATOR_PASSWORD),
                'status' => LifecycleStatus::Active->value,
                'preferred_locale' => 'en',
                'created_by_user_id' => $grantor->id,
            ],
        );

        foreach (['alpha', 'bravo'] as $name) {
            $user = User::query()->updateOrCreate(
                ['email' => "fixture.{$name}@example.test"],
                [
                    'name' => 'Fixture '.ucfirst($name),
                    'password' => Hash::make("synthetic-only-{$name}-password"),
                    'status' => LifecycleStatus::Active->value,
                    'preferred_locale' => 'en',
                    'created_by_user_id' => $grantor->id,
                ],
            );

            $tenant = Tenant::query()->updateOrCreate(
                ['slug' => "fixture-{$name}"],
                [
                    'name' => 'Fixture '.ucfirst($name),
                    'status' => LifecycleStatus::Active->value,
                    'default_locale' => 'en',
                    'timezone' => 'Africa/Cairo',
                    'data_residency_region' => 'eg',
                    'created_by_user_id' => $grantor->id,
                ],
            );

            TenantMembership::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                ['status' => LifecycleStatus::Active->value, 'created_by_user_id' => $grantor->id],
            );
        }
    }

    private function seedWorkforceAccounts(User $grantor): void
    {
        $accounts = [
            DemoAccounts::PRIMARY_DEMO_EMAIL => ['Meeting Demo Organizer', DemoAccounts::PRIMARY_DEMO_PASSWORD],
            DemoAccounts::ONSITE_EMAIL => ['On-Site Operator', DemoAccounts::ONSITE_PASSWORD],
            DemoAccounts::ACS_EMAIL => ['ACS Operator Demo', DemoAccounts::ACS_PASSWORD],
            DemoAccounts::TICKETING_EMAIL => ['Ticketing Manager Demo', DemoAccounts::TICKETING_PASSWORD],
        ];

        foreach ($accounts as $email => [$name, $password]) {
            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make($password),
                    'status' => LifecycleStatus::Active->value,
                    'preferred_locale' => 'en',
                    'created_by_user_id' => $grantor->id,
                ],
            );
        }
    }

    private function seedSystemRoles(User $grantor): void
    {
        $platformRoles = [
            'Super Administrator' => ['*'],
            'Security Auditor' => ['platform.audit.view', 'platform.audit.export', 'platform.audit.verify'],
            'Operations Viewer' => ['operations.health.view', 'platform.configuration.view'],
        ];

        foreach ($platformRoles as $name => $permissionKeys) {
            $role = PlatformRole::query()->updateOrCreate(
                ['name' => $name],
                ['description' => "{$name} system role.", 'is_system' => true, 'created_by_user_id' => $grantor->id],
            );
            $this->syncPlatformRolePermissions($role, $permissionKeys, $grantor);
        }

        $tenantRoles = [
            'Tenant Administrator' => ['*'],
            'Event Manager' => [
                'tenant.view', 'membership.view', 'role.view', 'audit.view', 'configuration.view',
                'event.view', 'event.manage', 'event.publish', 'event.cancel', 'event.reopen', 'event.archive',
                'registration.manage', 'ticketing.manage', 'order.view', 'order.manage',
                'attendee.view', 'attendee.manage', 'credential.view', 'credential.revoke', 'credential.reissue',
                'identity.configure', 'identity.review', 'identity.data.view',
                'wallet.pass.view', 'wallet.pass.generate', 'wallet.pass.manage',
                'checkin.scan.submit', 'checkin.dashboard.view', 'checkin.desk.perform',
                'kiosk.manage', 'kiosk.health.view',
                'badge.print', 'badge.reprint', 'badge.template.manage', 'attendee.walkup.register',
                'acs.configure', 'acs.events.view', 'acs.health.view',
            ],
            'Ticketing Manager' => [
                'event.view', 'ticketing.manage', 'order.view', 'order.manage',
                'payment.refund', 'attendee.view', 'credential.view', 'credential.revoke', 'credential.reissue',
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
        ];

        Tenant::query()->each(function (Tenant $tenant) use ($grantor, $tenantRoles): void {
            foreach ($tenantRoles as $name => $permissionKeys) {
                $role = TenantRole::query()->withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $name],
                    ['description' => "{$name} system role.", 'is_system' => true, 'created_by_user_id' => $grantor->id],
                );
                $this->syncTenantRolePermissions($tenant, $role, $permissionKeys, $grantor);
            }
        });
    }

    private function seedRoleAssignments(User $grantor): void
    {
        $assignments = [
            DemoAccounts::PLATFORM_ADMIN_EMAIL => [
                'platform_role' => 'Super Administrator',
                'tenant_slug' => 'fixture-alpha',
                'tenant_role' => 'Tenant Administrator',
            ],
            DemoAccounts::FIXTURE_CREATOR_EMAIL => [
                'platform_role' => 'Super Administrator',
                'tenant_slug' => 'fixture-alpha',
                'tenant_role' => 'Tenant Administrator',
            ],
            DemoAccounts::PRIMARY_DEMO_EMAIL => [
                'tenant_slug' => 'fixture-alpha',
                'tenant_role' => 'Tenant Administrator',
            ],
            DemoAccounts::FIXTURE_ALPHA_EMAIL => [
                'tenant_slug' => 'fixture-alpha',
                'tenant_role' => 'Tenant Administrator',
            ],
            DemoAccounts::FIXTURE_BRAVO_EMAIL => [
                'tenant_slug' => 'fixture-bravo',
                'tenant_role' => 'Event Manager',
            ],
            DemoAccounts::ONSITE_EMAIL => [
                'tenant_slug' => 'fixture-alpha',
                'tenant_role' => 'On-Site Staff',
            ],
            DemoAccounts::ACS_EMAIL => [
                'tenant_slug' => 'fixture-alpha',
                'tenant_role' => 'ACS Operator',
            ],
            DemoAccounts::TICKETING_EMAIL => [
                'tenant_slug' => 'fixture-alpha',
                'tenant_role' => 'Ticketing Manager',
            ],
        ];

        foreach ($assignments as $email => $config) {
            $user = User::query()->where('email', $email)->first();

            if (! $user instanceof User) {
                continue;
            }

            if (isset($config['platform_role'])) {
                $this->assignPlatformRole($user, $config['platform_role'], $grantor);
            }

            if (isset($config['tenant_slug'], $config['tenant_role'])) {
                $tenant = Tenant::query()->where('slug', $config['tenant_slug'])->first();

                if ($tenant instanceof Tenant) {
                    $membership = TenantMembership::query()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                        ['status' => LifecycleStatus::Active->value, 'created_by_user_id' => $grantor->id],
                    );

                    $this->assignTenantRole($tenant, $membership, $config['tenant_role'], $grantor);
                }
            }
        }
    }

    private function assignPlatformRole(User $user, string $roleName, User $grantor): void
    {
        $role = PlatformRole::query()->where('name', $roleName)->first();

        if (! $role instanceof PlatformRole) {
            return;
        }

        DB::table('platform_role_assignments')->updateOrInsert(
            ['user_id' => $user->id, 'platform_role_id' => $role->id, 'revoked_at' => null],
            [
                'granted_by_user_id' => $grantor->id,
                'expires_at' => null,
                'revoked_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function assignTenantRole(Tenant $tenant, TenantMembership $membership, string $roleName, User $grantor): void
    {
        $role = TenantRole::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('name', $roleName)
            ->first();

        if (! $role instanceof TenantRole) {
            return;
        }

        DB::table('tenant_role_assignments')->updateOrInsert(
            [
                'tenant_id' => $tenant->id,
                'tenant_membership_id' => $membership->id,
                'tenant_role_id' => $role->id,
                'revoked_at' => null,
            ],
            [
                'granted_by_user_id' => $grantor->id,
                'expires_at' => null,
                'revoked_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
