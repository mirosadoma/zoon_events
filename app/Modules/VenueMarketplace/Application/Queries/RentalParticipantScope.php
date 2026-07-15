<?php

namespace App\Modules\VenueMarketplace\Application\Queries;

use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;
use Illuminate\Database\Eloquent\Builder;

final class RentalParticipantScope
{
    public function query(int $actorTenantId, ?string $viewerRole = null): Builder
    {
        $query = RentalRequest::query()->withoutGlobalScopes();

        return match ($viewerRole) {
            'owner' => $query->where('tenant_id', $actorTenantId),
            'organizer' => $query->where('organizer_tenant_id', $actorTenantId),
            null => $query->where(fn (Builder $participant): Builder => $participant
                ->where('tenant_id', $actorTenantId)
                ->orWhere('organizer_tenant_id', $actorTenantId)),
            default => throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_RENTAL_NOT_FOUND),
        };
    }

    public function role(int $actorTenantId, RentalRequest $rental): string
    {
        if ((int) $rental->tenant_id === $actorTenantId) {
            return 'owner';
        }
        if ((int) $rental->organizer_tenant_id === $actorTenantId) {
            return 'organizer';
        }

        throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_RENTAL_NOT_FOUND);
    }
}
