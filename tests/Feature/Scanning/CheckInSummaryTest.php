<?php

namespace Tests\Feature\Scanning;

use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSummary;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\BuildsTenantFixtures;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('check-in')]
final class CheckInSummaryTest extends Phase2MySqlTestCase
{
    use AssertsProblemDetails;
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_checked_in_count_increases_only_for_accepted_and_manual_override_results(): void
    {
        $scan = $this->createIssuedCredentialScanFixture([
            'checkin.scan.submit',
            'checkin.dashboard.view',
        ]);
        $eventId = $scan['fixture']['event']->id;
        $scanUrl = "/api/v1/tenant/events/{$eventId}/scans";
        $summaryUrl = "/api/v1/tenant/events/{$eventId}/check-in-summary";

        $this->actingAsScanner($scan);
        $this->postJson($scanUrl, [
            'qr_payload' => $scan['token'],
            'scanner_type' => 'staff_phone',
        ], $this->scanHeaders($scan, 'summary-accepted-scan'))->assertOk();

        $this->actingAsScanner($scan);
        $this->getJson($summaryUrl, $this->tenantHeaders($scan['fixture']['tenant']))
            ->assertOk()
            ->assertJsonPath('data.checked_in_count', 1);

        $this->actingAsScanner($scan);
        $this->postJson($scanUrl, [
            'qr_payload' => $scan['token'],
            'scanner_type' => 'staff_phone',
        ], $this->scanHeaders($scan, 'summary-duplicate-scan'))->assertOk()
            ->assertJsonPath('data.result', 'duplicate');

        foreach (['rejected', 'revoked', 'expired'] as $result) {
            ScanEvent::query()->create([
                'tenant_id' => $scan['fixture']['tenant']->id,
                'event_id' => $eventId,
                'scanner_id' => $scan['scanner']->id,
                'scanner_type' => 'staff_phone',
                'direction' => 'in',
                'result' => $result,
                'reason' => 'synthetic',
                'offline_mode' => false,
                'scanned_at' => now(),
            ]);
        }

        EventCheckInSummary::query()
            ->where('tenant_id', $scan['fixture']['tenant']->id)
            ->where('event_id', $eventId)
            ->update([
                'checked_in_count' => 99,
                'rejected_count' => 0,
                'duplicate_count' => 0,
            ]);

        $this->artisan('zonetec:checkin:refresh-summary', ['--event' => $eventId])->assertSuccessful();

        $summary = EventCheckInSummary::query()
            ->where('tenant_id', $scan['fixture']['tenant']->id)
            ->where('event_id', $eventId)
            ->firstOrFail();

        self::assertSame(1, $summary->checked_in_count);
        self::assertSame(1, $summary->rejected_count);
        self::assertSame(3, $summary->duplicate_count);
    }
}
