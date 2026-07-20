<?php

namespace App\Modules\VenueMarketplace\Application\Queries;

use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\ControlDelegation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;

final readonly class GetParticipantDelegationQuery
{
    public function __construct(private RentalParticipantScope $scope) {}

    public function execute(int $actorTenantId, string $rentalPublicId): ControlDelegation
    {
        $rental = $this->scope->query($actorTenantId)
            ->where('public_id', $rentalPublicId)
            ->first();

        if ($rental === null) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_RENTAL_NOT_FOUND);
        }

        $delegation = ControlDelegation::query()
            ->withoutGlobalScopes()
            ->forParticipant($actorTenantId)
            ->where('rental_request_id', $rental->id)
            ->with('resources')
            ->first();

        if ($delegation === null) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_DELEGATION_NOT_FOUND);
        }

        return $delegation;
    }
}
