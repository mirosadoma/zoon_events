<?php

namespace App\Modules\VenueMarketplace\Testing\Fakes;

use App\Modules\Events\Application\Contracts\MarketplaceEventReader;
use App\Modules\Events\Application\Contracts\MarketplaceEventReadResult;

final class FakeMarketplaceEventReader implements MarketplaceEventReader
{
    public array $calls = [];

    public ?MarketplaceEventReadResult $result = null;

    public function readOwnedEvent(int $organizerTenantId, int $eventId): MarketplaceEventReadResult
    {
        $this->calls[] = compact('organizerTenantId', 'eventId');

        return $this->result ?? MarketplaceEventReadResult::denied('marketplace_event_not_found');
    }
}
