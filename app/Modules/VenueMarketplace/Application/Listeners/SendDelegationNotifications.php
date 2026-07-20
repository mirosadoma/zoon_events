<?php

namespace App\Modules\VenueMarketplace\Application\Listeners;

use App\Modules\VenueMarketplace\Domain\Events\DelegationProvisioned;
use App\Modules\VenueMarketplace\Domain\Events\DelegationReleased;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class SendDelegationNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public bool $afterCommit = true;

    public function __construct(private readonly Repository $cache) {}

    public function handleProvisioned(DelegationProvisioned $event): void
    {
        $dedupeKey = "marketplace:notification:delegation-provisioned:{$event->delegationPublicId}:{$event->status}";

        if (! $this->cache->add($dedupeKey, true, now()->addDay())) {
            return;
        }
    }

    public function handleReleased(DelegationReleased $event): void
    {
        $dedupeKey = "marketplace:notification:delegation-released:{$event->delegationPublicId}";

        if (! $this->cache->add($dedupeKey, true, now()->addDay())) {
            return;
        }
    }
}
