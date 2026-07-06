<?php

namespace App\Modules\WalletPasses\Application\Listeners;

use App\Modules\Credentials\Domain\Events\CredentialLifecycleChanged;
use App\Modules\WalletPasses\Application\Jobs\PushWalletPassUpdateJob;
use App\Modules\WalletPasses\Domain\WalletPassStatus;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;

final readonly class CredentialRevokedWalletSyncListener
{
    public function handle(CredentialLifecycleChanged $event): void
    {
        if ($event->transition !== 'revoked') {
            return;
        }

        WalletPass::query()
            ->where('tenant_id', $event->tenantId)
            ->where('event_id', $event->eventId)
            ->where('credential_id', $event->credentialId)
            ->whereNull('superseded_by_id')
            ->whereIn('status', [WalletPassStatus::Active, WalletPassStatus::Updated, WalletPassStatus::Created])
            ->get()
            ->each(function (WalletPass $pass): void {
                $pass->forceFill(['status' => WalletPassStatus::Revoked])->save();
                PushWalletPassUpdateJob::dispatch($pass->id, 'revoke');
            });
    }
}
