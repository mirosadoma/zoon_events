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
            ['key' => 'event.view', 'module' => 'events', 'description' => 'View tenant events.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'event.manage', 'module' => 'events', 'description' => 'Create and update tenant events.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'event.publish', 'module' => 'events', 'description' => 'Publish tenant events.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'event.cancel', 'module' => 'events', 'description' => 'Cancel tenant events.', 'scope' => 'tenant', 'risk_level' => 'privileged'],
            ['key' => 'event.reopen', 'module' => 'events', 'description' => 'Reopen event registration.', 'scope' => 'tenant', 'risk_level' => 'privileged'],
            ['key' => 'event.archive', 'module' => 'events', 'description' => 'Archive completed or cancelled events.', 'scope' => 'tenant', 'risk_level' => 'privileged'],
            ['key' => 'registration.manage', 'module' => 'registration', 'description' => 'Manage event registration forms.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'ticketing.manage', 'module' => 'ticketing', 'description' => 'Manage ticket types, pricing, and inventory.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'order.view', 'module' => 'orders', 'description' => 'View event orders.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'order.manage', 'module' => 'orders', 'description' => 'Manage event orders.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'payment.refund', 'module' => 'payments', 'description' => 'Request ticket payment refunds.', 'scope' => 'tenant', 'risk_level' => 'privileged'],
            ['key' => 'attendee.view', 'module' => 'attendees', 'description' => 'View event attendees.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'attendee.manage', 'module' => 'attendees', 'description' => 'Correct and manage attendee records.', 'scope' => 'tenant', 'risk_level' => 'privileged'],
            ['key' => 'credential.view', 'module' => 'credentials', 'description' => 'View credential status.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'credential.validate', 'module' => 'credentials', 'description' => 'Validate signed credentials.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'credential.revoke', 'module' => 'credentials', 'description' => 'Revoke signed credentials.', 'scope' => 'tenant', 'risk_level' => 'privileged'],
            ['key' => 'credential.reissue', 'module' => 'credentials', 'description' => 'Reissue signed credentials.', 'scope' => 'tenant', 'risk_level' => 'privileged'],
            ['key' => 'wallet.pass.view', 'module' => 'wallet-passes', 'description' => 'View wallet pass status.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'wallet.pass.generate', 'module' => 'wallet-passes', 'description' => 'Generate wallet passes for attendees.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'wallet.pass.manage', 'module' => 'wallet-passes', 'description' => 'Manage wallet pass lifecycle.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'checkin.scan.submit', 'module' => 'scanning', 'description' => 'Submit staff QR scans.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'checkin.scan.override', 'module' => 'scanning', 'description' => 'Override duplicate scan rejections.', 'scope' => 'tenant', 'risk_level' => 'privileged'],
            ['key' => 'checkin.dashboard.view', 'module' => 'scanning', 'description' => 'View event check-in dashboard.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'kiosk.manage', 'module' => 'kiosk', 'description' => 'Register, pair, and retire kiosk devices.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'kiosk.health.view', 'module' => 'kiosk', 'description' => 'View kiosk and printer health status.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'checkin.desk.perform', 'module' => 'scanning', 'description' => 'Perform attendee look-up and check-in from the manual desk.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'badge.print', 'module' => 'badge-printing', 'description' => 'Print attendee badges.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'badge.reprint', 'module' => 'badge-printing', 'description' => 'Reprint attendee badges with a stated reason.', 'scope' => 'tenant', 'risk_level' => 'privileged'],
            ['key' => 'badge.template.manage', 'module' => 'badge-printing', 'description' => 'Create, update, activate, and deactivate badge templates.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'attendee.walkup.register', 'module' => 'attendees', 'description' => 'Register walk-up attendees at the manual desk.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
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
