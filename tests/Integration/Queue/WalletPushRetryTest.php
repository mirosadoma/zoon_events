<?php

namespace Tests\Integration\Queue;

use App\Models\User;
use App\Modules\Events\Application\Actions\UpdateEvent;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use App\Modules\WalletPasses\Application\Jobs\PushWalletPassUpdateJob;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;
use App\Modules\WalletPasses\Testing\FakeWalletAdapter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase2WalletFixture;
use Tests\Support\Phase2MySqlTestCase;
use Tests\Support\UsesFakeWalletAdapters;

#[Group('phase-2')]
#[Group('wallet-passes')]
final class WalletPushRetryTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase2WalletFixture;
    use DatabaseTransactions;
    use UsesFakeWalletAdapters;

    public function test_unavailable_adapter_results_use_bounded_retry_and_event_update_still_completes(): void
    {
        $job = new PushWalletPassUpdateJob('01SYNTHETIC', 'update');
        self::assertSame(5, $job->tries);
        self::assertSame([30, 60, 120, 240, 480], $job->backoff());

        $scan = $this->createIssuedCredentialScanFixture();
        $pass = $this->createWalletPassForCredential(
            $scan,
            serial: '01UNAVAILABLE'.str_repeat('0', 14),
        );
        $pass->refresh();
        self::assertStringStartsWith('01UNAVAILABLE', $pass->pass_serial_number);
        self::assertNotNull(WalletPass::query()->find($pass->id));

        $adapter = app(FakeWalletAdapter::class);
        $threw = false;

        try {
            (new PushWalletPassUpdateJob($pass->id, 'update'))->handle();
        } catch (RuntimeException $exception) {
            $threw = true;
            self::assertSame('Wallet provider unavailable; retry required.', $exception->getMessage());
        }

        self::assertTrue($threw, 'Unavailable adapter must release the job for retry.');
        self::assertContains('update', array_column($adapter->calls(), 'operation'));

        $updated = app(UpdateEvent::class)->execute(
            $this->tenantContext($scan),
            $scan['fixture']['event'],
            ['location_name_en' => 'Retry Hall'],
        );

        self::assertSame('Retry Hall', $updated->location_name_en);
    }

    /** @param array{fixture:array<string,mixed>,membership:TenantMembership,scanner:User} $scan */
    private function tenantContext(array $scan): TenantContext
    {
        return new TenantContext($scan['fixture']['tenant'], $scan['membership'], $scan['scanner']);
    }
}
