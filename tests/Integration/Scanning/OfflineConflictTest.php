<?php

namespace Tests\Integration\Scanning;

use App\Modules\Scanning\Application\Actions\ReconcileOfflineScanBatchAction;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('offline-scanning')]
final class OfflineConflictTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_conflicting_offline_batches_keep_the_earliest_accepted_scan(): void
    {
        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        app(TenantContextStore::class)->bind(
            $scan['fixture']['tenant'],
            $scan['membership'],
            $scan['scanner'],
        );

        $later = now()->addMinutes(10)->format(DATE_ATOM);
        $earlier = now()->addMinute()->format(DATE_ATOM);
        $action = app(ReconcileOfflineScanBatchAction::class);

        $batchLater = $action->execute(
            $scan['fixture']['tenant']->id,
            $scan['fixture']['event']->id,
            'device-later',
            [['qr_payload' => $scan['token'], 'scanned_at' => $later]],
            $scan['scanner']->id,
        );

        $batchEarlier = $action->execute(
            $scan['fixture']['tenant']->id,
            $scan['fixture']['event']->id,
            'device-earlier',
            [['qr_payload' => $scan['token'], 'scanned_at' => $earlier]],
            $scan['scanner']->id,
        );

        self::assertSame(1, ScanEvent::query()
            ->where('credential_id', $scan['credential']->id)
            ->where('result', 'accepted')
            ->count());
        self::assertSame(1, ScanEvent::query()
            ->where('credential_id', $scan['credential']->id)
            ->where('result', 'duplicate')
            ->where('reason', 'offline_conflict_resolution')
            ->count());

        $batchLater->refresh();
        $batchEarlier->refresh();
        self::assertGreaterThan(0, $batchLater->conflict_count);
        self::assertGreaterThan(0, $batchEarlier->conflict_count);
        self::assertContains($batchLater->status, ['processed', 'processed_with_conflicts']);
        self::assertSame('processed_with_conflicts', $batchEarlier->status);
    }
}
