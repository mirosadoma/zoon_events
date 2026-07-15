<?php

namespace App\Modules\VenueMarketplace\Application\Queries;

use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;

final readonly class GetParticipantRentalQuery
{
    public function __construct(private RentalParticipantScope $scope) {}

    public function execute(int $actorTenantId, string $rentalPublicId): RentalRequest
    {
        return $this->scope->query($actorTenantId)
            ->where('public_id', $rentalPublicId)
            ->with('assets')
            ->first()
            ?? throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_RENTAL_NOT_FOUND);
    }
}
