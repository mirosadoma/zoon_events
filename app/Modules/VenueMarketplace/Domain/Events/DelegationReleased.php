<?php

namespace App\Modules\VenueMarketplace\Domain\Events;

final readonly class DelegationReleased
{
    public function __construct(
        public string $delegationPublicId,
        public int $ownerTenantId,
        public int $organizerTenantId,
        public string $correlationId,
    ) {}
}
