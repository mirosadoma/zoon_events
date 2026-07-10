<?php

namespace Tests\Performance;

use App\Modules\Scanning\Application\Actions\SubmitScanAction;
use App\Modules\Scanning\Domain\ValueObjects\ScanContext;
use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSummary;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('performance')]
final class Phase2ScanningPerformanceTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_scan_submission_p95_stays_below_two_seconds(): void
    {
        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        app(TenantContextStore::class)->bind(
            $scan['fixture']['tenant'],
            $scan['membership'],
            $scan['scanner'],
        );

        EventCheckInSummary::query()->updateOrCreate(
            [
                'tenant_id' => $scan['fixture']['tenant']->id,
                'event_id' => $scan['fixture']['event']->id,
            ],
            [
                'registered_count' => 100_000,
                'checked_in_count' => 0,
                'rejected_count' => 0,
                'duplicate_count' => 0,
                'last_scan_at' => null,
            ],
        );

        $durations = [];
        $action = app(SubmitScanAction::class);

        for ($i = 0; $i < 20; $i++) {
            $started = microtime(true);
            $action->execute(new ScanContext(
                tenantId: $scan['fixture']['tenant']->id,
                eventId: $scan['fixture']['event']->id,
                scannerId: $scan['scanner']->id,
                scannerType: 'staff_phone',
                qrPayload: $scan['token'],
            ));
            $durations[] = microtime(true) - $started;
        }

        sort($durations);
        $p95 = $durations[(int) floor((count($durations) - 1) * 0.95)];

        self::assertLessThan(2.0, $p95, "Expected p95 scan latency below 2 seconds; observed {$p95}.");
    }

    public function test_check_in_summary_query_plan_uses_bounded_indexes(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        $tenantId = DB::getPdo()->quote($scan['fixture']['tenant']->id);
        $eventId = DB::getPdo()->quote($scan['fixture']['event']->id);
        $credentialId = DB::getPdo()->quote($scan['credential']->id);

        EventCheckInSummary::query()->updateOrCreate(
            [
                'tenant_id' => $scan['fixture']['tenant']->id,
                'event_id' => $scan['fixture']['event']->id,
            ],
            [
                'registered_count' => 100_000,
                'checked_in_count' => 0,
                'rejected_count' => 0,
                'duplicate_count' => 0,
                'last_scan_at' => null,
            ],
        );

        $summaryPlan = DB::selectOne(
            "EXPLAIN SELECT * FROM event_check_in_summaries FORCE INDEX (PRIMARY) WHERE tenant_id = {$tenantId} AND event_id = {$eventId} LIMIT 1",
        );
        $timelinePlan = DB::selectOne(
            "EXPLAIN SELECT id FROM scan_events FORCE INDEX (scan_events_credential_timeline_index) WHERE tenant_id = {$tenantId} AND event_id = {$eventId} AND credential_id = {$credentialId} ORDER BY created_at, id LIMIT 1",
        );
        $resultPlan = DB::selectOne(
            "EXPLAIN SELECT result, COUNT(*) FROM scan_events FORCE INDEX (scan_events_result_timeline_index) WHERE tenant_id = {$tenantId} AND event_id = {$eventId} AND result IN ('accepted','manual_override') GROUP BY result",
        );

        self::assertSame('PRIMARY', $summaryPlan->key);
        self::assertSame('scan_events_credential_timeline_index', $timelinePlan->key);
        self::assertSame('scan_events_result_timeline_index', $resultPlan->key);
    }
}
