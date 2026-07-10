<?php

namespace Tests\Feature\WalletPasses;

use App\Models\User;
use App\Modules\Credentials\Application\Actions\ReissueCredential;
use App\Modules\Credentials\Application\Actions\RevokeCredential;
use App\Modules\Events\Application\Actions\UpdateEvent;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use App\Modules\WalletPasses\Application\Jobs\PushWalletPassUpdateJob;
use App\Modules\WalletPasses\Domain\WalletPassStatus;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;
use App\Modules\WalletPasses\Testing\FakeWalletAdapter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase2WalletFixture;
use Tests\Support\Phase2MySqlTestCase;
use Tests\Support\UsesFakeWalletAdapters;

#[Group('phase-2')]
#[Group('wallet-passes')]
final class CredentialSyncTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase2WalletFixture;
    use DatabaseTransactions;
    use UsesFakeWalletAdapters;

    public function test_revoking_a_credential_marks_wallet_pass_revoked_and_calls_adapter_revoke(): void
    {
        Queue::fake();

        $scan = $this->createIssuedCredentialScanFixture();
        $pass = $this->createWalletPassForCredential($scan);

        app(RevokeCredential::class)->execute(
            $this->tenantContext($scan),
            $scan['fixture']['event']->id,
            $scan['credential']->id,
            'Lost device',
        );

        self::assertSame(WalletPassStatus::Revoked, $pass->refresh()->status);
        Queue::assertPushed(
            PushWalletPassUpdateJob::class,
            function (PushWalletPassUpdateJob $job) use ($pass): bool {
                if ($job->walletPassId !== $pass->id || $job->operation !== 'revoke') {
                    return false;
                }

                $job->handle();

                return true;
            },
        );
        self::assertContains('revoke', array_column(app(FakeWalletAdapter::class)->calls(), 'operation'));
    }

    public function test_reissuing_a_credential_supersedes_prior_wallet_pass_and_excludes_it_from_event_sync(): void
    {
        Queue::fake();

        $scan = $this->createIssuedCredentialScanFixture();
        $pass = $this->createWalletPassForCredential($scan);

        app(ReissueCredential::class)->execute(
            $this->tenantContext($scan),
            $scan['fixture']['event']->id,
            $scan['credential']->id,
            'Replacement card',
        );

        $pass->refresh();
        self::assertNotNull($pass->superseded_by_id);
        self::assertTrue(
            WalletPass::query()->whereKey($pass->superseded_by_id)->where('credential_id', '!=', $scan['credential']->id)->exists(),
        );

        app(UpdateEvent::class)->execute(
            $this->tenantContext($scan),
            $scan['fixture']['event'],
            ['location_name_en' => 'New Hall'],
        );

        Queue::assertNotPushed(
            PushWalletPassUpdateJob::class,
            fn (PushWalletPassUpdateJob $job): bool => $job->walletPassId === $pass->id,
        );
    }

    /** @param array{fixture:array<string,mixed>,membership:TenantMembership,scanner:User} $scan */
    private function tenantContext(array $scan): TenantContext
    {
        return new TenantContext($scan['fixture']['tenant'], $scan['membership'], $scan['scanner']);
    }
}
