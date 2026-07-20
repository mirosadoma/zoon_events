<?php

namespace App\Modules\VenueMarketplace\Application\Listeners;

use App\Modules\VenueMarketplace\Domain\Events\DisputeResolved;
use App\Modules\VenueMarketplace\Domain\Events\StatementRevised;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class SendSettlementDisputeNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public bool $afterCommit = true;

    public function __construct(private readonly Repository $cache) {}

    public function handle(DisputeResolved|StatementRevised $event): void
    {
        $dedupeKey = match (true) {
            $event instanceof DisputeResolved => "marketplace:notification:dispute-resolved:{$event->disputePublicId}:{$event->decision}",
            $event instanceof StatementRevised => "marketplace:notification:statement-revised:{$event->statementPublicId}:{$event->revision}",
        };

        if (! $this->cache->add($dedupeKey, true, now()->addDay())) {
            return;
        }
    }
}
