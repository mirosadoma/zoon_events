<?php

namespace Tests\Feature\Scanning;

use App\Modules\Scanning\Application\Actions\ReconcileOfflineScanBatchAction;
use App\Modules\Scanning\Application\Actions\ScanDecisionEvaluatorImpl;
use App\Modules\Scanning\Application\Actions\SubmitScanAction;
use App\Modules\Scanning\Domain\ValueObjects\ScanContext;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('offline-scanning')]
final class OfflineReconciliationTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_non_conflicting_offline_batch_matches_online_evaluator_outcomes(): void
    {
        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        app(TenantContextStore::class)->bind(
            $scan['fixture']['tenant'],
            $scan['membership'],
            $scan['scanner'],
        );

        $context = new ScanContext(
            tenantId: $scan['fixture']['tenant']->id,
            eventId: $scan['fixture']['event']->id,
            scannerId: $scan['scanner']->id,
            scannerType: 'staff_phone',
            qrPayload: $scan['token'],
            offlineMode: true,
            scannedAt: now()->subMinute(),
        );

        $expected = app(ScanDecisionEvaluatorImpl::class)->evaluate($context);

        $batch = app(ReconcileOfflineScanBatchAction::class)->execute(
            $scan['fixture']['tenant']->id,
            $scan['fixture']['event']->id,
            'device-alpha',
            [[
                'qr_payload' => $scan['token'],
                'scanned_at' => $context->scannedAt?->format(DATE_ATOM),
            ]],
            $scan['scanner']->id,
        );

        self::assertSame('processed', $batch->status);
        self::assertSame(1, $batch->accepted_count);
        self::assertSame($expected->result, ScanEvent::query()->latest('created_at')->value('result'));

        $online = app(SubmitScanAction::class)->execute(new ScanContext(
            tenantId: $scan['fixture']['tenant']->id,
            eventId: $scan['fixture']['event']->id,
            scannerId: $scan['scanner']->id,
            scannerType: 'staff_phone',
            qrPayload: $scan['token'],
        ));

        self::assertSame('duplicate', $online->decision->result);
    }
}
