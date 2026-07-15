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

/**
 * Seeds the complete foundation: permissions, platform roles, tenants (with
 * organization types), workforce accounts, tenant roles, and role assignments.
 *
 * Every user gets exactly the permissions their role defines — nothing more.
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
            $grantor = $this->seedPlatformAdmin();

            $this->seedPlatformUsers($grantor);
            $this->seedPlatformRoles($grantor);
            $this->seedPlatformRoleAssignments($grantor);

            $tenants = $this->seedTenants($grantor);
            $this->seedTenantUsers($grantor);
            $this->seedTenantRoles($tenants, $grantor);
            $this->seedTenantMembershipsAndAssignments($tenants, $grantor);
        });
    }

    // ─── Platform Admin ──────────────────────────────────────

    private function seedPlatformAdmin(): User
    {
        $email = (string) config('zonetec.bootstrap_admin_email', DemoAccounts::PLATFORM_ADMIN_EMAIL);
        $password = (string) config('zonetec.bootstrap_admin_password', DemoAccounts::PLATFORM_ADMIN_PASSWORD);

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

    // ─── Platform Users ──────────────────────────────────────

    private function seedPlatformUsers(User $grantor): void
    {
        $accounts = [
            DemoAccounts::SECURITY_AUDITOR_EMAIL => ['Security Auditor', DemoAccounts::SECURITY_AUDITOR_PASSWORD, 'en'],
            DemoAccounts::OPS_VIEWER_EMAIL       => ['Operations Viewer', DemoAccounts::OPS_VIEWER_PASSWORD, 'en'],
            DemoAccounts::FIXTURE_CREATOR_EMAIL   => ['Fixture Creator', DemoAccounts::FIXTURE_CREATOR_PASSWORD, 'en'],
        ];

        foreach ($accounts as $email => [$name, $password, $locale]) {
            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make($password),
                    'status' => LifecycleStatus::Active->value,
                    'preferred_locale' => $locale,
                    'created_by_user_id' => $grantor->id,
                ],
            );
        }
    }

    // ─── Platform Roles ──────────────────────────────────────

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
            'Platform Marketplace Viewer' => [
                'platform.marketplace.view',
            ],
            'Platform Dispute Manager' => [
                'platform.marketplace.view',
                'platform.marketplace.disputes.manage',
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

    // ─── Platform Role Assignments ───────────────────────────

    private function seedPlatformRoleAssignments(User $grantor): void
    {
        $map = [
            DemoAccounts::PLATFORM_ADMIN_EMAIL   => 'Super Administrator',
            DemoAccounts::FIXTURE_CREATOR_EMAIL   => 'Super Administrator',
            DemoAccounts::SECURITY_AUDITOR_EMAIL  => 'Security Auditor',
            DemoAccounts::OPS_VIEWER_EMAIL        => 'Operations Viewer',
        ];

        foreach ($map as $email => $roleName) {
            $this->assignPlatformRole(
                User::query()->where('email', $email)->first(),
                $roleName,
                $grantor,
            );
        }
    }

    // ─── Tenants (with organization_type) ────────────────────

    /** @return array<string, Tenant> */
    private function seedTenants(User $grantor): array
    {
        $definitions = [
            'fixture-alpha' => [
                'name' => 'Alpha Events & Venues',
                'organization_type' => 'venue_owner',
                'default_locale' => 'en',
                'timezone' => 'Africa/Cairo',
                'data_residency_region' => 'eg',
            ],
            'fixture-bravo' => [
                'name' => 'Bravo Conferences',
                'organization_type' => 'organizer',
                'default_locale' => 'ar',
                'timezone' => 'Asia/Riyadh',
                'data_residency_region' => 'sa',
            ],
            'fixture-charlie' => [
                'name' => 'Charlie Hybrid Corp',
                'organization_type' => 'hybrid',
                'default_locale' => 'en',
                'timezone' => 'Asia/Dubai',
                'data_residency_region' => 'ae',
            ],
        ];

        $tenants = [];

        foreach ($definitions as $slug => $attrs) {
            $tenants[$slug] = Tenant::query()->updateOrCreate(
                ['slug' => $slug],
                array_merge($attrs, [
                    'status' => LifecycleStatus::Active->value,
                    'created_by_user_id' => $grantor->id,
                ]),
            );
        }

        return $tenants;
    }

    // ─── Tenant Users ────────────────────────────────────────

    private function seedTenantUsers(User $grantor): void
    {
        $accounts = [
            // Alpha users
            DemoAccounts::ALPHA_ADMIN_EMAIL          => ['Alpha Admin', DemoAccounts::ALPHA_ADMIN_PASSWORD, 'en'],
            DemoAccounts::ALPHA_EVENT_MGR_EMAIL      => ['Alpha Event Manager', DemoAccounts::ALPHA_EVENT_MGR_PASSWORD, 'en'],
            DemoAccounts::ALPHA_TICKETING_EMAIL      => ['Alpha Ticketing Manager', DemoAccounts::ALPHA_TICKETING_PASSWORD, 'en'],
            DemoAccounts::ALPHA_ONSITE_EMAIL         => ['Alpha On-Site Staff', DemoAccounts::ALPHA_ONSITE_PASSWORD, 'en'],
            DemoAccounts::ALPHA_ACS_EMAIL            => ['Alpha ACS Operator', DemoAccounts::ALPHA_ACS_PASSWORD, 'en'],
            DemoAccounts::ALPHA_VENUE_ADMIN_EMAIL    => ['Alpha Venue Owner Admin', DemoAccounts::ALPHA_VENUE_ADMIN_PASSWORD, 'en'],
            DemoAccounts::ALPHA_ASSET_MGR_EMAIL      => ['Alpha Venue Asset Manager', DemoAccounts::ALPHA_ASSET_MGR_PASSWORD, 'en'],
            DemoAccounts::ALPHA_RENTAL_APPROVER_EMAIL => ['Alpha Rental Approver', DemoAccounts::ALPHA_RENTAL_APPROVER_PASSWORD, 'en'],
            DemoAccounts::ALPHA_FINANCE_EMAIL        => ['Alpha Finance Manager', DemoAccounts::ALPHA_FINANCE_PASSWORD, 'en'],
            DemoAccounts::FIXTURE_ALPHA_EMAIL        => ['Fixture Alpha', DemoAccounts::FIXTURE_ALPHA_PASSWORD, 'en'],

            // Bravo users
            DemoAccounts::BRAVO_ADMIN_EMAIL          => ['Bravo Admin', DemoAccounts::BRAVO_ADMIN_PASSWORD, 'ar'],
            DemoAccounts::BRAVO_EVENT_MGR_EMAIL      => ['Bravo Event Manager', DemoAccounts::BRAVO_EVENT_MGR_PASSWORD, 'ar'],
            DemoAccounts::FIXTURE_BRAVO_EMAIL        => ['Fixture Bravo', DemoAccounts::FIXTURE_BRAVO_PASSWORD, 'en'],

            // Charlie users
            DemoAccounts::CHARLIE_ADMIN_EMAIL        => ['Charlie Admin', DemoAccounts::CHARLIE_ADMIN_PASSWORD, 'en'],
        ];

        foreach ($accounts as $email => [$name, $password, $locale]) {
            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make($password),
                    'status' => LifecycleStatus::Active->value,
                    'preferred_locale' => $locale,
                    'created_by_user_id' => $grantor->id,
                ],
            );
        }
    }

    // ─── Tenant Roles ────────────────────────────────────────

    /** @param array<string, Tenant> $tenants */
    private function seedTenantRoles(array $tenants, User $grantor): void
    {
        $roles = [
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
                'marketplace.manage',
            ],

            'Ticketing Manager' => [
                'event.view',
                'ticketing.manage',
                'order.view', 'order.manage',
                'payment.refund',
                'attendee.view',
                'credential.view', 'credential.revoke', 'credential.reissue',
                'wallet.pass.view', 'wallet.pass.generate',
            ],

            'On-Site Staff' => [
                'event.view',
                'attendee.view',
                'credential.view',
                'checkin.scan.submit', 'checkin.scan.override', 'checkin.dashboard.view', 'checkin.desk.perform',
                'kiosk.health.view',
                'badge.print', 'badge.reprint',
                'attendee.walkup.register',
            ],

            'ACS Operator' => [
                'event.view',
                'acs.configure', 'acs.events.view', 'acs.health.view', 'acs.emergency.manage',
            ],

            'Venue Owner Admin' => [
                'venue.manage',
                'rentals.approve',
                'reports.view',
                'audit.view',
            ],

            'Venue Asset Manager' => [
                'venue.manage',
            ],

            'Venue Rental Approver' => [
                'rentals.approve',
            ],

            'Venue Finance Manager' => [
                'reports.view',
                'audit.view',
            ],
        ];

        foreach ($tenants as $tenant) {
            foreach ($roles as $name => $permissionKeys) {
                $role = TenantRole::query()->withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $name],
                    ['description' => "{$name} system role.", 'is_system' => true, 'created_by_user_id' => $grantor->id],
                );
                $this->syncTenantRolePermissions($tenant, $role, $permissionKeys, $grantor);
            }
        }
    }

    // ─── Memberships & Role Assignments ──────────────────────

    /** @param array<string, Tenant> $tenants */
    private function seedTenantMembershipsAndAssignments(array $tenants, User $grantor): void
    {
        $assignments = [
            // ── Alpha (venue_owner): full workforce ──────────
            'fixture-alpha' => [
                DemoAccounts::PLATFORM_ADMIN_EMAIL        => 'Tenant Administrator',
                DemoAccounts::FIXTURE_CREATOR_EMAIL        => 'Tenant Administrator',
                DemoAccounts::ALPHA_ADMIN_EMAIL            => 'Tenant Administrator',
                DemoAccounts::FIXTURE_ALPHA_EMAIL          => 'Tenant Administrator',
                DemoAccounts::ALPHA_EVENT_MGR_EMAIL        => 'Event Manager',
                DemoAccounts::ALPHA_TICKETING_EMAIL        => 'Ticketing Manager',
                DemoAccounts::ALPHA_ONSITE_EMAIL           => 'On-Site Staff',
                DemoAccounts::ALPHA_ACS_EMAIL              => 'ACS Operator',
                DemoAccounts::ALPHA_VENUE_ADMIN_EMAIL      => 'Venue Owner Admin',
                DemoAccounts::ALPHA_ASSET_MGR_EMAIL        => 'Venue Asset Manager',
                DemoAccounts::ALPHA_RENTAL_APPROVER_EMAIL  => 'Venue Rental Approver',
                DemoAccounts::ALPHA_FINANCE_EMAIL          => 'Venue Finance Manager',
            ],

            // ── Bravo (organizer): event team only ───────────
            'fixture-bravo' => [
                DemoAccounts::BRAVO_ADMIN_EMAIL     => 'Tenant Administrator',
                DemoAccounts::BRAVO_EVENT_MGR_EMAIL => 'Event Manager',
                DemoAccounts::FIXTURE_BRAVO_EMAIL   => 'Event Manager',
            ],

            // ── Charlie (hybrid): admin only ─────────────────
            'fixture-charlie' => [
                DemoAccounts::CHARLIE_ADMIN_EMAIL => 'Tenant Administrator',
            ],
        ];

        foreach ($assignments as $slug => $emailRoleMap) {
            $tenant = $tenants[$slug] ?? null;

            if (! $tenant instanceof Tenant) {
                continue;
            }

            foreach ($emailRoleMap as $email => $roleName) {
                $user = User::query()->where('email', $email)->first();

                if (! $user instanceof User) {
                    continue;
                }

                $membership = TenantMembership::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                    ['status' => LifecycleStatus::Active->value, 'created_by_user_id' => $grantor->id],
                );

                $this->assignTenantRole($tenant, $membership, $roleName, $grantor);
            }
        }
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function assignPlatformRole(?User $user, string $roleName, User $grantor): void
    {
        if (! $user instanceof User) {
            return;
        }

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
