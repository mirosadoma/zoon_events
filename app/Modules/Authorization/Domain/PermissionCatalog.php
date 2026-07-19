<?php

namespace App\Modules\Authorization\Domain;

/**
 * Single source of truth for all platform and tenant permissions.
 * Used by seeders, role management UI, and policy evaluators.
 */
final class PermissionCatalog
{
    /**
     * @return array<int, array{key: string, module: string, description: string, scope: string, risk_level: string}>
     */
    public static function all(): array
    {
        return [
            // ── Tenancy ──────────────────────────────────────
            ['key' => 'tenant.view', 'module' => 'tenancy', 'description' => 'View tenant foundation data.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'membership.view', 'module' => 'tenancy', 'description' => 'View tenant memberships.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'membership.manage', 'module' => 'tenancy', 'description' => 'Manage tenant memberships.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'configuration.view', 'module' => 'tenancy', 'description' => 'View tenant configuration.', 'scope' => 'tenant', 'risk_level' => 'standard'],

            // ── Authorization ────────────────────────────────
            ['key' => 'role.view', 'module' => 'authorization', 'description' => 'View tenant roles.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'role.manage', 'module' => 'authorization', 'description' => 'Manage tenant roles.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'role.assign', 'module' => 'authorization', 'description' => 'Assign tenant roles.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],

            // ── Audit ────────────────────────────────────────
            ['key' => 'audit.view', 'module' => 'audit', 'description' => 'View tenant audit logs.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'audit.export', 'module' => 'audit', 'description' => 'Export tenant audit logs.', 'scope' => 'tenant', 'risk_level' => 'privileged'],
            ['key' => 'audit.verify', 'module' => 'audit', 'description' => 'Verify tenant audit integrity.', 'scope' => 'tenant', 'risk_level' => 'privileged'],

            // ── Feature Flags ────────────────────────────────
            ['key' => 'feature_flag.view', 'module' => 'feature-flags', 'description' => 'View tenant feature flags.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'feature_flag.manage', 'module' => 'feature-flags', 'description' => 'Manage tenant feature flags.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],

            // ── Events ───────────────────────────────────────
            ['key' => 'event.view', 'module' => 'events', 'description' => 'View tenant events.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'event.manage', 'module' => 'events', 'description' => 'Create and update tenant events.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'event.publish', 'module' => 'events', 'description' => 'Publish tenant events.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'event.cancel', 'module' => 'events', 'description' => 'Cancel tenant events.', 'scope' => 'tenant', 'risk_level' => 'privileged'],
            ['key' => 'event.reopen', 'module' => 'events', 'description' => 'Reopen event registration.', 'scope' => 'tenant', 'risk_level' => 'privileged'],
            ['key' => 'event.archive', 'module' => 'events', 'description' => 'Archive completed or cancelled events.', 'scope' => 'tenant', 'risk_level' => 'privileged'],

            // ── Categories ───────────────────────────────────
            ['key' => 'category.view', 'module' => 'events', 'description' => 'View event tier categories.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'category.manage', 'module' => 'events', 'description' => 'Manage event tier categories and privileges.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],

            // ── Registration ─────────────────────────────────
            ['key' => 'registration.manage', 'module' => 'registration', 'description' => 'Manage event registration forms.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],

            // ── Ticketing ────────────────────────────────────
            ['key' => 'ticketing.manage', 'module' => 'ticketing', 'description' => 'Manage ticket types, pricing, and inventory.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],

            // ── Orders ───────────────────────────────────────
            ['key' => 'order.view', 'module' => 'orders', 'description' => 'View event orders.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'order.manage', 'module' => 'orders', 'description' => 'Manage event orders.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],

            // ── Payments ─────────────────────────────────────
            ['key' => 'payment.refund', 'module' => 'payments', 'description' => 'Request ticket payment refunds.', 'scope' => 'tenant', 'risk_level' => 'privileged'],

            // ── Attendees ────────────────────────────────────
            ['key' => 'attendee.view', 'module' => 'attendees', 'description' => 'View event attendees.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'attendee.manage', 'module' => 'attendees', 'description' => 'Correct and manage attendee records.', 'scope' => 'tenant', 'risk_level' => 'privileged'],
            ['key' => 'attendee.walkup.register', 'module' => 'attendees', 'description' => 'Register walk-up attendees at the manual desk.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],

            // ── Credentials ──────────────────────────────────
            ['key' => 'credential.view', 'module' => 'credentials', 'description' => 'View credential status.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'credential.validate', 'module' => 'credentials', 'description' => 'Validate signed credentials.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'credential.revoke', 'module' => 'credentials', 'description' => 'Revoke signed credentials.', 'scope' => 'tenant', 'risk_level' => 'privileged'],
            ['key' => 'credential.reissue', 'module' => 'credentials', 'description' => 'Reissue signed credentials.', 'scope' => 'tenant', 'risk_level' => 'privileged'],

            // ── Identity Verification ────────────────────────
            ['key' => 'identity.configure', 'module' => 'identity-verification', 'description' => 'Configure identity verification requirements.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'identity.review', 'module' => 'identity-verification', 'description' => 'Review identity verification submissions.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'identity.data.view', 'module' => 'identity-verification', 'description' => 'View sensitive identity verification metadata.', 'scope' => 'tenant', 'risk_level' => 'privileged'],
            ['key' => 'identity.data.manage', 'module' => 'identity-verification', 'description' => 'Delete identity data and run compliance actions.', 'scope' => 'tenant', 'risk_level' => 'privileged'],

            // ── Wallet Passes ────────────────────────────────
            ['key' => 'wallet.pass.view', 'module' => 'wallet-passes', 'description' => 'View wallet pass status.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'wallet.pass.generate', 'module' => 'wallet-passes', 'description' => 'Generate wallet passes for attendees.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'wallet.pass.manage', 'module' => 'wallet-passes', 'description' => 'Manage wallet pass lifecycle.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],

            // ── Scanning / Check-in ─────────────────────────
            ['key' => 'checkin.scan.submit', 'module' => 'scanning', 'description' => 'Submit staff QR scans.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'checkin.scan.override', 'module' => 'scanning', 'description' => 'Override duplicate scan rejections.', 'scope' => 'tenant', 'risk_level' => 'privileged'],
            ['key' => 'checkin.dashboard.view', 'module' => 'scanning', 'description' => 'View event check-in dashboard.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'checkin.desk.perform', 'module' => 'scanning', 'description' => 'Perform check-in from the manual desk.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],

            // ── Kiosk ────────────────────────────────────────
            ['key' => 'kiosk.manage', 'module' => 'kiosk', 'description' => 'Register, pair, and retire kiosk devices.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'kiosk.health.view', 'module' => 'kiosk', 'description' => 'View kiosk and printer health status.', 'scope' => 'tenant', 'risk_level' => 'standard'],

            // ── Badge Printing ───────────────────────────────
            ['key' => 'badge.print', 'module' => 'badge-printing', 'description' => 'Print attendee badges.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'badge.reprint', 'module' => 'badge-printing', 'description' => 'Reprint attendee badges with a stated reason.', 'scope' => 'tenant', 'risk_level' => 'privileged'],
            ['key' => 'badge.template.manage', 'module' => 'badge-printing', 'description' => 'Manage badge templates.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],

            // ── Access Control (ACS) ─────────────────────────
            ['key' => 'acs.configure', 'module' => 'access-control', 'description' => 'Configure ACS zones, lanes, and rules.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'acs.events.view', 'module' => 'access-control', 'description' => 'View gate access events.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'acs.health.view', 'module' => 'access-control', 'description' => 'View ACS lane health.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'acs.emergency.manage', 'module' => 'access-control', 'description' => 'Raise and clear emergency egress signals.', 'scope' => 'tenant', 'risk_level' => 'privileged'],

            // ── Venue Marketplace ────────────────────────────
            ['key' => 'venue.manage', 'module' => 'venue-marketplace', 'description' => 'Manage venue profiles, assets, and publication.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'marketplace.manage', 'module' => 'venue-marketplace', 'description' => 'Browse catalog and submit rental requests.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'rentals.approve', 'module' => 'venue-marketplace', 'description' => 'Approve and reject rental requests.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],
            ['key' => 'reports.view', 'module' => 'venue-marketplace', 'description' => 'View and export marketplace statements.', 'scope' => 'tenant', 'risk_level' => 'standard'],

            // ── Subscriptions ────────────────────────────────
            ['key' => 'subscription.view', 'module' => 'subscriptions', 'description' => 'View subscription plans and status.', 'scope' => 'tenant', 'risk_level' => 'standard'],
            ['key' => 'subscription.manage', 'module' => 'subscriptions', 'description' => 'Manage tenant subscription.', 'scope' => 'tenant', 'risk_level' => 'sensitive'],

            // ══════════════════════════════════════════════════
            // Platform-scoped permissions
            // ══════════════════════════════════════════════════
            ['key' => 'platform.event.view', 'module' => 'events', 'description' => 'View all events across tenants.', 'scope' => 'platform', 'risk_level' => 'standard'],
            ['key' => 'platform.user.view', 'module' => 'identity', 'description' => 'View platform admin users.', 'scope' => 'platform', 'risk_level' => 'standard'],
            ['key' => 'platform.user.manage', 'module' => 'identity', 'description' => 'Create and manage platform admin users.', 'scope' => 'platform', 'risk_level' => 'privileged'],
            ['key' => 'platform.role.view', 'module' => 'authorization', 'description' => 'View platform roles.', 'scope' => 'platform', 'risk_level' => 'standard'],
            ['key' => 'platform.role.manage', 'module' => 'authorization', 'description' => 'Manage platform roles and assign permissions.', 'scope' => 'platform', 'risk_level' => 'privileged'],
            ['key' => 'platform.tenant.view', 'module' => 'tenancy', 'description' => 'View platform tenants.', 'scope' => 'platform', 'risk_level' => 'standard'],
            ['key' => 'platform.tenant.manage', 'module' => 'tenancy', 'description' => 'Manage platform tenants.', 'scope' => 'platform', 'risk_level' => 'privileged'],
            ['key' => 'platform.access.recover', 'module' => 'authorization', 'description' => 'Perform platform recovery actions.', 'scope' => 'platform', 'risk_level' => 'privileged'],
            ['key' => 'platform.audit.view', 'module' => 'audit', 'description' => 'View platform audit logs.', 'scope' => 'platform', 'risk_level' => 'privileged'],
            ['key' => 'platform.audit.export', 'module' => 'audit', 'description' => 'Export platform audit logs.', 'scope' => 'platform', 'risk_level' => 'privileged'],
            ['key' => 'platform.audit.verify', 'module' => 'audit', 'description' => 'Verify platform audit integrity.', 'scope' => 'platform', 'risk_level' => 'privileged'],
            ['key' => 'operations.health.view', 'module' => 'operations', 'description' => 'View detailed platform health.', 'scope' => 'platform', 'risk_level' => 'standard'],
            ['key' => 'platform.feature_flag.view', 'module' => 'feature-flags', 'description' => 'View platform feature flags.', 'scope' => 'platform', 'risk_level' => 'standard'],
            ['key' => 'platform.feature_flag.manage', 'module' => 'feature-flags', 'description' => 'Manage platform feature flags.', 'scope' => 'platform', 'risk_level' => 'privileged'],
            ['key' => 'platform.configuration.view', 'module' => 'operations', 'description' => 'View platform configuration schemas.', 'scope' => 'platform', 'risk_level' => 'standard'],
            ['key' => 'platform.marketplace.view', 'module' => 'venue-marketplace', 'description' => 'View cross-participant marketplace oversight.', 'scope' => 'platform', 'risk_level' => 'standard'],
            ['key' => 'platform.marketplace.disputes.manage', 'module' => 'venue-marketplace', 'description' => 'Resolve marketplace disputes.', 'scope' => 'platform', 'risk_level' => 'privileged'],
            ['key' => 'platform.subscription.view', 'module' => 'subscriptions', 'description' => 'View all subscription plans.', 'scope' => 'platform', 'risk_level' => 'standard'],
            ['key' => 'platform.subscription.manage', 'module' => 'subscriptions', 'description' => 'Manage subscription plans.', 'scope' => 'platform', 'risk_level' => 'privileged'],
        ];
    }

    /** @return array<int, array{key: string, module: string, description: string, scope: string, risk_level: string}> */
    public static function tenant(): array
    {
        return array_values(array_filter(self::all(), fn (array $p): bool => $p['scope'] === 'tenant'));
    }

    /** @return array<int, array{key: string, module: string, description: string, scope: string, risk_level: string}> */
    public static function platform(): array
    {
        return array_values(array_filter(self::all(), fn (array $p): bool => $p['scope'] === 'platform'));
    }

    /** @return list<string> */
    public static function tenantKeys(): array
    {
        return array_map(fn (array $p): string => $p['key'], self::tenant());
    }

    /** @return list<string> */
    public static function platformKeys(): array
    {
        return array_map(fn (array $p): string => $p['key'], self::platform());
    }

    /** @return array<string, list<array{key: string, module: string, description: string, scope: string, risk_level: string}>> */
    public static function groupedByModule(string $scope): array
    {
        $filtered = $scope === 'platform' ? self::platform() : self::tenant();
        $grouped = [];
        foreach ($filtered as $permission) {
            $grouped[$permission['module']][] = $permission;
        }

        return $grouped;
    }
}
