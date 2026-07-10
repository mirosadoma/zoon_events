<?php

namespace Tests\Integration\Security;

use App\Models\User;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Scanning\Application\Actions\ReconcileOfflineScanBatchAction;
use App\Modules\Scanning\Application\Actions\SubmitScanAction;
use App\Modules\Scanning\Domain\ValueObjects\ScanContext;
use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSummary;
use App\Modules\Scanning\Infrastructure\Persistence\Models\OfflineScanReconciliationBatch;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\WalletPasses\Application\Jobs\PushWalletPassUpdateJob;
use App\Modules\WalletPasses\Domain\WalletPassStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase2WalletFixture;
use Tests\Support\Phase2MySqlTestCase;
use Tests\Support\UsesFakeWalletAdapters;

/**
 * Consolidates the highest-risk Phase 2 security assertions into one always-run suite.
 */
#[Group('phase-2')]
final class Phase2CriticalRegressionTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase2WalletFixture;
    use DatabaseTransactions;
    use UsesFakeWalletAdapters;

    public function test_cross_tenant_and_cross_event_wallet_requests_match_unknown_reference_responses(): void
    {
        $fixtureA = $this->createRegistrationFixture(domainReference: 'tenant-a.example.test');
        $this->createRegistrationFixture(domainReference: 'tenant-b.example.test');

        $created = $this->withHeader('Idempotency-Key', 'phase2-regression-wallet')
            ->postJson("http://tenant-a.example.test/api/v1/public/events/{$fixtureA['event']->slug}/registrations", $this->registrationPayload($fixtureA))
            ->assertCreated();

        $reference = $created->json('data.public_reference');
        $token = $created->json('data.access_token');

        $unknown = $this->withHeader('X-Order-Access-Token', 'wrong')
            ->getJson('http://tenant-a.example.test/api/v1/public/orders/ord_unknown/wallet-passes/apple');

        $wrongHost = $this->withHeader('X-Order-Access-Token', $token)
            ->getJson("http://tenant-b.example.test/api/v1/public/orders/{$reference}/wallet-passes/apple");

        $wrongToken = $this->withHeader('X-Order-Access-Token', 'wrong')
            ->getJson("http://tenant-a.example.test/api/v1/public/orders/{$reference}/wallet-passes/google");

        foreach ([$unknown, $wrongHost, $wrongToken] as $response) {
            $response->assertNotFound()->assertJsonPath('code', 'resource_not_found');
            self::assertSame($unknown->json('detail'), $response->json('detail'));
        }
    }

    public function test_cross_event_scan_is_indistinguishable_from_unknown_credential(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        $otherEvent = Event::query()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'slug' => 'regression-other-'.uniqid(),
            'name_en' => 'Other Event',
            'name_ar' => 'فعالية أخرى',
            'tier' => 'public',
            'status' => 'published',
            'timezone' => 'Africa/Cairo',
            'start_at' => '2027-02-10 12:00:00',
            'end_at' => '2027-02-10 18:00:00',
            'registration_opens_at' => '2026-01-01 00:00:00',
            'registration_closes_at' => '2027-02-10 11:00:00',
            'capacity' => 10,
            'created_by_user_id' => $scan['fixture']['actor']->id,
            'published_by_user_id' => $scan['fixture']['actor']->id,
            'published_at' => now(),
        ]);

        app(TenantContextStore::class)->bind(
            $scan['fixture']['tenant'],
            $scan['membership'],
            $scan['scanner'],
        );

        $foreign = app(SubmitScanAction::class)->execute(new ScanContext(
            tenantId: $scan['fixture']['tenant']->id,
            eventId: $otherEvent->id,
            scannerId: $scan['scanner']->id,
            scannerType: 'staff_phone',
            qrPayload: $scan['token'],
        ));
        $unknown = app(SubmitScanAction::class)->execute(new ScanContext(
            tenantId: $scan['fixture']['tenant']->id,
            eventId: $scan['fixture']['event']->id,
            scannerId: $scan['scanner']->id,
            scannerType: 'staff_phone',
            qrPayload: 'zt1.invalid-token',
        ));

        self::assertSame($foreign->decision->result, $unknown->decision->result);
        self::assertSame($foreign->decision->reasonCode, $unknown->decision->reasonCode);
        self::assertNull($foreign->attendeeDisplayName);
        self::assertNull($unknown->attendeeDisplayName);
    }

    public function test_forced_audit_failure_leaves_no_scan_or_check_in_mutations(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        app(TenantContextStore::class)->bind(
            $scan['fixture']['tenant'],
            $scan['membership'],
            $scan['scanner'],
        );

        app()->instance(AuditWriter::class, new class implements AuditWriter
        {
            public function write(
                string $scope,
                ?string $tenantId,
                string $action,
                string $outcome,
                ?User $actor = null,
                ?string $reasonCode = null,
                ?string $targetType = null,
                ?string $targetId = null,
                array $metadata = [],
                ?array $changeSummary = null,
            ): AuditLog {
                if (str_starts_with($action, 'scan.')) {
                    throw new RuntimeException('Synthetic scan audit failure.');
                }

                return new AuditLog;
            }
        });

        try {
            app(SubmitScanAction::class)->execute(new ScanContext(
                tenantId: $scan['fixture']['tenant']->id,
                eventId: $scan['fixture']['event']->id,
                scannerId: $scan['scanner']->id,
                scannerType: 'staff_phone',
                qrPayload: $scan['token'],
            ));
            self::fail('Expected audit failure to abort the scan transaction.');
        } catch (RuntimeException $exception) {
            self::assertSame('Synthetic scan audit failure.', $exception->getMessage());
        }

        self::assertSame(0, DB::table('scan_events')->where('tenant_id', $scan['fixture']['tenant']->id)->count());
        self::assertSame(
            'not_checked_in',
            Attendee::query()->findOrFail($scan['credential']->attendee_id)->checkin_status,
        );
        self::assertNull(EventCheckInSummary::query()
            ->where('tenant_id', $scan['fixture']['tenant']->id)
            ->where('event_id', $scan['fixture']['event']->id)
            ->first());
    }

    public function test_wallet_sync_audit_rows_exclude_provider_payload_and_certificate_material(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        $updatedPass = $this->createWalletPassForCredential($scan, status: WalletPassStatus::Active);
        $failedUpdatePass = $this->createWalletPassForCredential(
            $scan,
            provider: 'google',
            status: WalletPassStatus::Active,
            serial: '01UNKNOWN'.str_repeat('0', 19),
        );
        $revokedPass = $this->createWalletPassForCredential($scan, status: WalletPassStatus::Active);
        $failedRevokePass = $this->createWalletPassForCredential(
            $scan,
            provider: 'google',
            status: WalletPassStatus::Active,
            serial: '01UNKNOWN'.str_repeat('1', 19),
        );

        (new PushWalletPassUpdateJob($updatedPass->id, 'update'))->handle();
        (new PushWalletPassUpdateJob($failedUpdatePass->id, 'update'))->handle();

        $revokedPass->forceFill(['status' => WalletPassStatus::Revoked])->save();
        (new PushWalletPassUpdateJob($revokedPass->id, 'revoke'))->handle();

        $failedRevokePass->forceFill(['status' => WalletPassStatus::Revoked])->save();
        (new PushWalletPassUpdateJob($failedRevokePass->id, 'revoke'))->handle();

        foreach ([
            'wallet_pass.updated',
            'wallet_pass.update_failed',
            'wallet_pass.revoked',
            'wallet_pass.revocation_failed',
        ] as $action) {
            $audit = DB::table('audit_logs')->where('action', $action)->latest('occurred_at')->first();
            self::assertNotNull($audit, "Missing audit row for {$action}");
            $encoded = json_encode($audit);
            self::assertStringNotContainsString('certificate', strtolower($encoded));
            self::assertStringNotContainsString('payload', strtolower($encoded));
            self::assertStringNotContainsString('secret', strtolower($encoded));
        }
    }

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
        self::assertInstanceOf(OfflineScanReconciliationBatch::class, $batchLater);
        self::assertGreaterThan(0, $batchLater->conflict_count);
        self::assertGreaterThan(0, $batchEarlier->conflict_count);
        self::assertSame('processed_with_conflicts', $batchEarlier->status);
    }
}
