<?php

namespace App\Modules\Events\Application\Contracts;

interface MarketplaceEventReader
{
    public function readOwnedEvent(int $organizerTenantId, int $eventId): MarketplaceEventReadResult;
}
