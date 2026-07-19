<?php

namespace Database\Seeders;

/**
 * Centralises every demo credential so tests and seeders reference one place.
 */
final class DemoAccounts
{
    // ── Platform Admin (مشرف) ───────────────────────────────
    public const ADMIN_EMAIL    = 'super.admin@admin.com';
    public const ADMIN_PASSWORD = 'admin1234';

    // ── Tenant / Organizer (منظم) ──────────────────────────
    public const TENANT_EMAIL    = 'organizer@zonetec.test';
    public const TENANT_PASSWORD = 'Organizer2026!';
    public const TENANT_ORG_NAME = 'Zonetec Events';
    public const TENANT_SLUG     = 'zonetec-events';

    // Backwards-compat aliases
    public const PLATFORM_ADMIN_EMAIL    = self::ADMIN_EMAIL;
    public const PLATFORM_ADMIN_PASSWORD = self::ADMIN_PASSWORD;
    public const PRIMARY_DEMO_EMAIL      = self::TENANT_EMAIL;
    public const PRIMARY_DEMO_PASSWORD   = self::TENANT_PASSWORD;
}
