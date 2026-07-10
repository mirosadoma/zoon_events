<?php

namespace Tests\Integration\Security;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsTenantFixtures;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('check-in')]
#[Group('phase-2-isolation')]
final class CheckInDashboardIsolationTest extends Phase2MySqlTestCase
{
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_organizer_never_sees_another_event_or_tenant_summary_values(): void
    {
        $scanA = $this->createIssuedCredentialScanFixture(['checkin.scan.submit', 'checkin.dashboard.view']);
        $scanB = $this->createIssuedCredentialScanFixture(['checkin.dashboard.view']);

        $this->actingAsScanner($scanA);
        $this->postJson(
            "/api/v1/tenant/events/{$scanA['fixture']['event']->id}/scans",
            ['qr_payload' => $scanA['token'], 'scanner_type' => 'staff_phone'],
            $this->scanHeaders($scanA, 'isolation-accepted-scan'),
        )->assertOk();

        $this->actingAsScanner($scanA);
        $summaryA = $this->getJson(
            "/api/v1/tenant/events/{$scanA['fixture']['event']->id}/check-in-summary",
            $this->tenantHeaders($scanA['fixture']['tenant']),
        )->assertOk()->json('data.checked_in_count');

        $this->actingAsScanner($scanA);
        $this->getJson(
            "/api/v1/tenant/events/{$scanB['fixture']['event']->id}/check-in-summary",
            $this->tenantHeaders($scanA['fixture']['tenant']),
        )->assertNotFound();

        $this->actingAsScanner($scanB);
        $summaryB = $this->getJson(
            "/api/v1/tenant/events/{$scanB['fixture']['event']->id}/check-in-summary",
            $this->tenantHeaders($scanB['fixture']['tenant']),
        )->assertOk()->json('data.checked_in_count');

        self::assertSame(1, $summaryA);
        self::assertSame(0, $summaryB);

        $this->actingAsScanner($scanA);
        $this->getJson(
            "/api/v1/tenant/events/{$scanA['fixture']['event']->id}/check-in-summary",
            $this->tenantHeaders($scanB['fixture']['tenant']),
        )->assertNotFound();
    }
}
