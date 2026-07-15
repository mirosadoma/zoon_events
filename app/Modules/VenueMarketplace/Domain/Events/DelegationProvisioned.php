<?php

namespace App\Modules\VenueMarketplace\Domain\Events;

final readonly class DelegationProvisioned
{
    public function __construct(
        public string $delegationPublicId,
        public string $status,
        public int $ownerTenantId,
        public int $organizerTenantId,
        public string $correlationId,
    ) {}
}
