<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\VenueMarketplace\Application\Services\ReservationReleaseService;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\RentalStateMachine;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\ControlDelegation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;
use Illuminate\Support\Facades\DB;

final readonly class ExpireRentalAction
{
    public function __construct(
        private RentalStateMachine $states,
        private ReservationReleaseService $reservations,
        private ReleaseDelegatedAssetsAction $releaser,
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

            if (! in_array($rental->status, ['active', 'approved'], true)) {
                return $rental;
            }

            $now = now()->toDateTimeImmutable();

            if ($now < $rental->requested_end_at->toDateTimeImmutable()) {
                return $rental;
            }

            $this->states->transition(
                $rental->status,
                'completed',
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

            if ($delegation !== null && $delegation->expired_at === null && ! in_array($delegation->status, ['revoked', 'completed'], true)) {
                $this->releaser->execute($ownerTenantId, $delegation->public_id, $correlationId);

                $delegation->forceFill([
                    'status' => 'expired',
                    'expired_at' => $now,
                    'version' => ((int) $delegation->version) + 1,
                ])->save();
            }

            $this->reservations->release($rental, 'rental_completed', $now);

            $rental->forceFill([
                'status' => 'completed',
                'version' => ((int) $rental->version) + 1,
                'completed_at' => $now,
            ])->save();

            return $rental->fresh();
        });
    }
}
