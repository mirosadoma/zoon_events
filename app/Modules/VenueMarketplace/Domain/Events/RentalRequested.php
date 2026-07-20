<?php

namespace App\Modules\VenueMarketplace\Domain\Events;

final readonly class RentalRequested
{
    public string $ownerParticipantId;

    public string $organizerParticipantId;

    public function __construct(
        public string $rentalPublicId,
        public string $eventPublicId,
        private int $ownerTenantId,
        private int $organizerTenantId,
        public int $actorUserId,
        public string $status,
        public int $totalMinor,
        public string $currency,
        public string $correlationId,
        public string $idempotencyKeyHash,
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
