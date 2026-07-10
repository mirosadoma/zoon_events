<?php

namespace Tests\Feature\WalletPasses;

use App\Models\User;
use App\Modules\Events\Application\Actions\UpdateEvent;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use App\Modules\WalletPasses\Application\Jobs\PushWalletPassUpdateJob;
use App\Modules\WalletPasses\Domain\WalletPassStatus;
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
final class EventChangeSyncTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase2WalletFixture;
    use DatabaseTransactions;
    use UsesFakeWalletAdapters;

    public function test_event_detail_changes_dispatch_update_jobs_only_for_active_or_updated_passes(): void
    {
        Queue::fake();

        $scan = $this->createIssuedCredentialScanFixture();
        $active = $this->createWalletPassForCredential($scan, status: WalletPassStatus::Active);
        $updated = $this->createWalletPassForCredential($scan, provider: 'google', status: WalletPassStatus::Updated);
        $revoked = $this->createWalletPassForCredential($scan, status: WalletPassStatus::Revoked);
        $expired = $this->createWalletPassForCredential($scan, status: WalletPassStatus::Expired);
        $failed = $this->createWalletPassForCredential($scan, status: WalletPassStatus::Failed);

        app(UpdateEvent::class)->execute(
            $this->tenantContext($scan),
            $scan['fixture']['event'],
            ['location_name_en' => 'Updated Hall'],
        );

        Queue::assertPushed(PushWalletPassUpdateJob::class, 2);
        Queue::assertPushed(
            PushWalletPassUpdateJob::class,
            fn (PushWalletPassUpdateJob $job): bool => $job->walletPassId === $active->id && $job->operation === 'update',
        );
        Queue::assertPushed(
            PushWalletPassUpdateJob::class,
            fn (PushWalletPassUpdateJob $job): bool => $job->walletPassId === $updated->id && $job->operation === 'update',
        );
        Queue::assertNotPushed(
            PushWalletPassUpdateJob::class,
            fn (PushWalletPassUpdateJob $job): bool => in_array($job->walletPassId, [$revoked->id, $expired->id, $failed->id], true),
        );
    }

    /** @param array{fixture:array<string,mixed>,membership:TenantMembership,scanner:User} $scan */
    private function tenantContext(array $scan): TenantContext
    {
        return new TenantContext($scan['fixture']['tenant'], $scan['membership'], $scan['scanner']);
    }
}
