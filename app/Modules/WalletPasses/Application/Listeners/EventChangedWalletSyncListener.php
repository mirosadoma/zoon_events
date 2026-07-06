<?php

namespace App\Modules\WalletPasses\Application\Listeners;

use App\Modules\Events\Domain\Events\EventUpdated;
use App\Modules\WalletPasses\Application\Jobs\PushWalletPassUpdateJob;
use App\Modules\WalletPasses\Domain\WalletPassStatus;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;

final readonly class EventChangedWalletSyncListener
{
    public function handle(EventUpdated $event): void
    {
        WalletPass::query()
            ->where('tenant_id', $event->tenantId)
            ->where('event_id', $event->eventId)
            ->whereNull('superseded_by_id')
            ->whereIn('status', [WalletPassStatus::Active, WalletPassStatus::Updated])
            ->pluck('id')
            ->each(fn (string $walletPassId): mixed => PushWalletPassUpdateJob::dispatch($walletPassId, 'update'));
    }
}
