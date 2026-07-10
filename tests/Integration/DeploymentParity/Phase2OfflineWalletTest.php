<?php

namespace Tests\Integration\DeploymentParity;

use App\Modules\Scanning\Application\Actions\SubmitScanAction;
use App\Modules\Scanning\Application\Queries\GetCheckInSummaryQuery;
use App\Modules\Scanning\Domain\ValueObjects\ScanContext;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\WalletPasses\Application\Actions\GenerateWalletPassAction;
use App\Modules\WalletPasses\Application\Jobs\PushWalletPassUpdateJob;
use App\Modules\WalletPasses\Contracts\WalletAdapter;
use App\Modules\WalletPasses\Domain\Results\WalletAdapterResult;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassGenerationRequest;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassRevocationRequest;
use App\Modules\WalletPasses\Domain\ValueObjects\WalletPassUpdateRequest;
use App\Modules\WalletPasses\Domain\WalletPassStatus;
use App\Modules\WalletPasses\Testing\FakeWalletAdapter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase2WalletFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('deployment-parity')]
final class Phase2OfflineWalletTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase2WalletFixture;
    use DatabaseTransactions;

    public function test_scanning_continues_while_wallet_adapters_are_unreachable(): void
    {
        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit', 'checkin.dashboard.view']);
        app(TenantContextStore::class)->bind(
            $scan['fixture']['tenant'],
            $scan['membership'],
            $scan['scanner'],
        );
        app()->instance(FakeWalletAdapter::class, new class implements WalletAdapter
        {
            public function generate(WalletPassGenerationRequest $request): WalletAdapterResult
            {
                return new WalletAdapterResult('unavailable', null, 'wallet_provider_unavailable');
            }

            public function update(WalletPassUpdateRequest $request): WalletAdapterResult
            {
                return new WalletAdapterResult('unavailable', null, 'wallet_provider_unavailable');
            }

            public function revoke(WalletPassRevocationRequest $request): WalletAdapterResult
            {
                return new WalletAdapterResult('unavailable', null, 'wallet_provider_unavailable');
            }
        });

        config()->set('wallet.default_apple_adapter', 'fake');
        config()->set('wallet.default_google_adapter', 'fake');

        $degraded = app(GenerateWalletPassAction::class)->execute(
            $scan['fixture']['tenant']->id,
            $scan['fixture']['event']->id,
            $scan['credential']->attendee_id,
            $scan['credential']->id,
            'google',
        );

        self::assertSame(WalletPassStatus::Failed, $degraded->status);
        self::assertSame('wallet_provider_unavailable', $degraded->last_push_reason_code);

        $pendingUpdate = $this->createWalletPassForCredential(
            $scan,
            provider: 'apple',
            status: WalletPassStatus::Active,
            serial: '01UNAVAILABLE'.str_repeat('0', 15),
        );

        try {
            (new PushWalletPassUpdateJob($pendingUpdate->id, 'update'))->handle();
            self::fail('Unavailable wallet provider should request a retry.');
        } catch (RuntimeException $exception) {
            self::assertSame('Wallet provider unavailable; retry required.', $exception->getMessage());
        }

        self::assertSame('wallet_provider_unavailable', $pendingUpdate->refresh()->last_push_reason_code);

        $submission = app(SubmitScanAction::class)->execute(new ScanContext(
            tenantId: $scan['fixture']['tenant']->id,
            eventId: $scan['fixture']['event']->id,
            scannerId: $scan['scanner']->id,
            scannerType: 'staff_phone',
            qrPayload: $scan['token'],
        ));
        $summary = app(GetCheckInSummaryQuery::class)->handle(
            $scan['fixture']['tenant']->id,
            $scan['fixture']['event']->id,
        );

        self::assertSame('accepted', $submission->decision->result);
        self::assertSame(1, $summary->checked_in_count);
        self::assertSame(0, $summary->rejected_count);
    }
}
