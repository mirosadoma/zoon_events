<?php

namespace Tests\Integration\Security;

use App\Modules\WalletPasses\Application\Jobs\PushWalletPassUpdateJob;
use App\Modules\WalletPasses\Domain\WalletPassStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase2WalletFixture;
use Tests\Support\Phase2MySqlTestCase;
use Tests\Support\UsesFakeWalletAdapters;

#[Group('phase-2')]
#[Group('wallet-passes')]
final class WalletSyncAuditTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase2WalletFixture;
    use DatabaseTransactions;
    use UsesFakeWalletAdapters;

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
}
