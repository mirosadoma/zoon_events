<?php

namespace App\Modules\VenueMarketplace\Application\Listeners;

use App\Modules\VenueMarketplace\Domain\Events\RentalDecided;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class SendRentalDecisionNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public bool $afterCommit = true;

    public function __construct(private readonly Repository $cache) {}

    public function handle(RentalDecided $event): void
    {
        $dedupeKey = "marketplace:notification:rental-decision:{$event->rentalPublicId}:{$event->decision}";

        if (! $this->cache->add($dedupeKey, true, now()->addDay())) {
            return;
        }

        // Recipient resolution and delivery remain Notifications-owned. The queued
        // event carries only opaque identifiers used to fetch an authorized view.
    }
}
