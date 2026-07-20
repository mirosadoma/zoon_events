<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\RentalStateMachine;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\ControlDelegation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;
use Illuminate\Support\Facades\DB;

final readonly class ActivateRentalAction
{
    public function __construct(
        private RentalStateMachine $states,
        private ProvisionDelegatedAssetsAction $provisioner,
    ) {}

    public function execute(int $ownerTenantId, int $rentalId, string $correlationId): RentalRequest
    {
        return DB::transaction(function () use ($ownerTenantId, $rentalId, $correlationId): RentalRequest {
            $rental = RentalRequest::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $ownerTenantId)
                ->where('id', $rentalId)
                ->lockForUpdate()
                ->first();

            if ($rental === null) {
                throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_RENTAL_NOT_FOUND);
            }

            if ($rental->status !== 'approved') {
                return $rental;
            }

            $now = now()->toDateTimeImmutable();

            if ($now < $rental->requested_start_at->toDateTimeImmutable()) {
                return $rental;
            }

            if ($rental->status === 'revoked' || $rental->status === 'cancelled') {
                return $rental;
            }

            $this->states->transition(
                $rental->status,
                'active',
                'system',
                null,
                (int) $rental->version,
                (int) $rental->version,
                $now,
            );

            $delegation = ControlDelegation::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $ownerTenantId)
                ->where('rental_request_id', $rental->id)
                ->lockForUpdate()
                ->first();

            if ($delegation !== null && $delegation->isPending()) {
                $this->provisioner->execute($ownerTenantId, $delegation->public_id, $correlationId);
            }

            $rental->forceFill([
                'status' => 'active',
                'version' => ((int) $rental->version) + 1,
            ])->save();

            return $rental->fresh();
        });
    }
}
