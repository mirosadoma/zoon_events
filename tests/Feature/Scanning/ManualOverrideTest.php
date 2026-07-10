<?php

namespace Tests\Feature\Scanning;

use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\BuildsTenantFixtures;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('check-in')]
final class ManualOverrideTest extends Phase2MySqlTestCase
{
    use AssertsProblemDetails;
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_manual_override_requires_permission_reason_and_never_records_accepted(): void
    {
        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        $eventId = $scan['fixture']['event']->id;
        $url = "/api/v1/tenant/events/{$eventId}/scans";

        $this->actingAsScanner($scan);
        $this->postJson($url, [
            'qr_payload' => $scan['token'],
            'scanner_type' => 'staff_phone',
        ], $this->scanHeaders($scan, 'initial-accepted-scan'))->assertOk();

        $this->actingAsScanner($scan);
        $this->postJson($url, [
            'qr_payload' => $scan['token'],
            'scanner_type' => 'staff_phone',
            'override' => true,
            'override_reason' => 'VIP escort',
        ], $this->scanHeaders($scan, 'override-test-403-key'))
            ->assertForbidden()
            ->assertJsonPath('code', 'override_not_permitted');

        $this->grantTenantPermissions($scan['fixture']['tenant'], $scan['membership'], [
            'checkin.scan.submit', 'checkin.scan.override',
        ]);

        $this->actingAsScanner($scan);
        $this->postJson($url, [
            'qr_payload' => $scan['token'],
            'scanner_type' => 'staff_phone',
            'override' => true,
        ], $this->scanHeaders($scan, 'override-test-422-key'))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'override_reason_required');

        $this->actingAsScanner($scan);
        $response = $this->postJson($url, [
            'qr_payload' => $scan['token'],
            'scanner_type' => 'staff_phone',
            'override' => true,
            'override_reason' => 'VIP escort',
        ], $this->scanHeaders($scan, 'override-test-200-key'))->assertOk();

        self::assertSame('manual_override', $response->json('data.result'));
        self::assertSame(
            'manual_override',
            ScanEvent::query()->findOrFail($response->json('data.scan_event_id'))->result,
        );
        self::assertTrue(DB::table('audit_logs')
            ->where('tenant_id', $scan['fixture']['tenant']->id)
            ->where('action', 'scan.manual_override')
            ->exists());
    }
}
