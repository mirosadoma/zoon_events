<?php

namespace App\Modules\VenueMarketplace\Application\Audit;

interface MarketplaceAuditWriter
{
    public function write(MarketplaceAuditEvent $event): void;
}
