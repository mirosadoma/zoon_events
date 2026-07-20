<?php

namespace App\Modules\VenueMarketplace\Domain\Events;

use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;

final readonly class VenueCatalogEvents
{
    public function __construct(public MarketplaceAuditEvent $audit) {}
}
