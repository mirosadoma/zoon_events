<?php

namespace App\Modules\VenueMarketplace\Domain\Events;

final readonly class RentalDecided
{
    public string $ownerParticipantId;

    public string $organizerParticipantId;

    public function __construct(
        public string $rentalPublicId,
        public string $decision,
        private int $ownerTenantId,
        private int $organizerTenantId,
        public int $actorUserId,
        public string $status,
        public ?string $reasonCode,
        public string $correlationId,
    ) {
        $this->ownerParticipantId = hash('sha256', "tenant:{$ownerTenantId}");
        $this->organizerParticipantId = hash('sha256', "tenant:{$organizerTenantId}");
    }

    public function ownerTenantId(): int
    {
        return $this->ownerTenantId;
    }

    public function organizerTenantId(): int
    {
        return $this->organizerTenantId;
    }
}
