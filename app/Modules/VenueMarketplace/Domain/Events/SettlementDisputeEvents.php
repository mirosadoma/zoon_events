<?php

namespace App\Modules\VenueMarketplace\Domain\Events;

final readonly class StatementGenerated
{
    public function __construct(
        public string $statementPublicId,
        public int $revision,
        private int $ownerTenantId,
        private int $organizerTenantId,
        public string $rentalOutcome,
        public string $currency,
        public int $agreedTotalMinor,
        public string $correlationId,
    ) {}

    public function ownerTenantId(): int
    {
        return $this->ownerTenantId;
    }

    public function organizerTenantId(): int
    {
        return $this->organizerTenantId;
    }
}

final readonly class StatementRevised
{
    public function __construct(
        public string $statementPublicId,
        public int $revision,
        private int $ownerTenantId,
        private int $organizerTenantId,
        public int $actorUserId,
        public string $correlationId,
    ) {}

    public function ownerTenantId(): int
    {
        return $this->ownerTenantId;
    }

    public function organizerTenantId(): int
    {
        return $this->organizerTenantId;
    }
}

final readonly class DisputeOpened
{
    public function __construct(
        public string $disputePublicId,
        private int $ownerTenantId,
        private int $organizerTenantId,
        public int $actorUserId,
        public string $reasonCode,
        public string $correlationId,
    ) {}

    public function ownerTenantId(): int
    {
        return $this->ownerTenantId;
    }

    public function organizerTenantId(): int
    {
        return $this->organizerTenantId;
    }
}

final readonly class DisputeResolved
{
    public function __construct(
        public string $disputePublicId,
        public string $decision,
        private int $ownerTenantId,
        private int $organizerTenantId,
        public int $actorUserId,
        public ?string $resolutionCode,
        public string $correlationId,
    ) {}

    public function ownerTenantId(): int
    {
        return $this->ownerTenantId;
    }

    public function organizerTenantId(): int
    {
        return $this->organizerTenantId;
    }
}
