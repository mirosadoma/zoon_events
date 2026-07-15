<?php

namespace Database\Seeders;

/**
 * Centralises every demo credential so tests and seeders reference one place.
 *
 * Password rules: min-12, mixed case, digit, symbol.
 * Emails use @zonetec.test (workforce), @example.test (fixture / synthetic).
 */
final class DemoAccounts
{
    // ── Platform ──────────────────────────────────────────────
    public const PLATFORM_ADMIN_EMAIL    = 'super.admin@admin.com';
    public const PLATFORM_ADMIN_PASSWORD = 'admin1234';

    public const SECURITY_AUDITOR_EMAIL    = 'auditor@zonetec.test';
    public const SECURITY_AUDITOR_PASSWORD = 'AuditSecure2026!';

    public const OPS_VIEWER_EMAIL    = 'ops.viewer@zonetec.test';
    public const OPS_VIEWER_PASSWORD = 'OpsViewer2026!';

    // ── Tenant: Alpha (venue_owner) ──────────────────────────
    public const ALPHA_ADMIN_EMAIL    = 'alpha.admin@zonetec.test';
    public const ALPHA_ADMIN_PASSWORD = 'AlphaAdmin2026!';

    public const ALPHA_EVENT_MGR_EMAIL    = 'alpha.events@zonetec.test';
    public const ALPHA_EVENT_MGR_PASSWORD = 'AlphaEvents2026!';

    public const ALPHA_TICKETING_EMAIL    = 'alpha.ticketing@zonetec.test';
    public const ALPHA_TICKETING_PASSWORD = 'AlphaTicket2026!';

    public const ALPHA_ONSITE_EMAIL    = 'alpha.onsite@zonetec.test';
    public const ALPHA_ONSITE_PASSWORD = 'AlphaOnsite2026!';

    public const ALPHA_ACS_EMAIL    = 'alpha.acs@zonetec.test';
    public const ALPHA_ACS_PASSWORD = 'AlphaAcs2026!!';

    public const ALPHA_VENUE_ADMIN_EMAIL    = 'alpha.venue.admin@zonetec.test';
    public const ALPHA_VENUE_ADMIN_PASSWORD = 'AlphaVenue2026!';

    public const ALPHA_ASSET_MGR_EMAIL    = 'alpha.assets@zonetec.test';
    public const ALPHA_ASSET_MGR_PASSWORD = 'AlphaAsset2026!';

    public const ALPHA_RENTAL_APPROVER_EMAIL    = 'alpha.rentals@zonetec.test';
    public const ALPHA_RENTAL_APPROVER_PASSWORD = 'AlphaRental2026!';

    public const ALPHA_FINANCE_EMAIL    = 'alpha.finance@zonetec.test';
    public const ALPHA_FINANCE_PASSWORD = 'AlphaFinance2026!';

    // ── Tenant: Bravo (organizer) ────────────────────────────
    public const BRAVO_ADMIN_EMAIL    = 'bravo.admin@zonetec.test';
    public const BRAVO_ADMIN_PASSWORD = 'BravoAdmin2026!';

    public const BRAVO_EVENT_MGR_EMAIL    = 'bravo.events@zonetec.test';
    public const BRAVO_EVENT_MGR_PASSWORD = 'BravoEvents2026!';

    // ── Tenant: Charlie (hybrid) ─────────────────────────────
    public const CHARLIE_ADMIN_EMAIL    = 'charlie.admin@zonetec.test';
    public const CHARLIE_ADMIN_PASSWORD = 'CharlieAdmin2026!';

    // ── Fixture / Synthetic (tests only) ─────────────────────
    public const FIXTURE_CREATOR_EMAIL    = 'fixture.creator@example.test';
    public const FIXTURE_CREATOR_PASSWORD = 'synthetic-only-creator-password';

    public const FIXTURE_ALPHA_EMAIL    = 'fixture.alpha@example.test';
    public const FIXTURE_ALPHA_PASSWORD = 'synthetic-only-alpha-password';

    public const FIXTURE_BRAVO_EMAIL    = 'fixture.bravo@example.test';
    public const FIXTURE_BRAVO_PASSWORD = 'synthetic-only-bravo-password';

    // Backwards-compat aliases used by older tests
    public const PRIMARY_DEMO_EMAIL    = self::ALPHA_ADMIN_EMAIL;
    public const PRIMARY_DEMO_PASSWORD = self::ALPHA_ADMIN_PASSWORD;
    public const ONSITE_EMAIL          = self::ALPHA_ONSITE_EMAIL;
    public const ONSITE_PASSWORD       = self::ALPHA_ONSITE_PASSWORD;
    public const ACS_EMAIL             = self::ALPHA_ACS_EMAIL;
    public const ACS_PASSWORD          = self::ALPHA_ACS_PASSWORD;
    public const TICKETING_EMAIL       = self::ALPHA_TICKETING_EMAIL;
    public const TICKETING_PASSWORD    = self::ALPHA_TICKETING_PASSWORD;
}
