<?php

namespace Database\Seeders;

/**
 * Centralises every demo credential so tests and seeders reference one place.
 */
final class DemoAccounts
{
    // ── Platform Admin (مشرف) ───────────────────────────────
    public const ADMIN_EMAIL = 'super.admin@admin.com';

    public const ADMIN_PASSWORD = 'admin1234';

    // ── Tenant / Organizer (منظم) ──────────────────────────
    public const TENANT_EMAIL = 'demo@zonetec.test';

    public const TENANT_PASSWORD = 'DemoMeet2026!';

    public const TENANT_ORG_NAME = 'Zonetec Events';

    public const TENANT_SLUG = 'zonetec-events';

    // ── Operational staff ─────────────────────────────────
    public const TICKETING_EMAIL = 'ticketing@zonetec.test';

    public const TICKETING_PASSWORD = 'TicketDemo2026!';

    public const ONSITE_EMAIL = 'onsite@zonetec.test';

    public const ONSITE_PASSWORD = 'OnsiteDemo2026!';

    public const ACS_EMAIL = 'acs@zonetec.test';

    public const ACS_PASSWORD = 'AcsDemo2026!';

    // ── Visitor portal ────────────────────────────────────
    public const VISITOR_EMAIL = 'visitor@zonetec.test';

    public const VISITOR_PASSWORD = 'VisitorDemo2026!';

    // Backwards-compat aliases
    public const PLATFORM_ADMIN_EMAIL = self::ADMIN_EMAIL;

    public const PLATFORM_ADMIN_PASSWORD = self::ADMIN_PASSWORD;

    public const PRIMARY_DEMO_EMAIL = self::TENANT_EMAIL;

    public const PRIMARY_DEMO_PASSWORD = self::TENANT_PASSWORD;
}
