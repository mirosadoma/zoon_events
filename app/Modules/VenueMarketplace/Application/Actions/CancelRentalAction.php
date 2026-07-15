<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Services\ReservationReleaseService;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\RentalStateMachine;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Audit\DatabaseMarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\ControlDelegation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;

final readonly class CancelRentalAction
{
    public function __construct(
        private AuditedTransaction $transactions,
        private RentalStateMachine $states,
        private ReservationReleaseService $reservations,
        private DatabaseMarketplaceAuditWriter $audit,
    ) {}

    public function execute(
        int $organizerTenantId,
        int $actorUserId,
        string $rentalPublicId,
        int $expectedVersion,
        string $correlationId,
        ?string $reason = null,
    ): RentalRequest {
        $reason = $reason === null ? null : trim($reason);

        return $this->transactions->run(
            function () use (
                $organizerTenantId, $actorUserId, $rentalPublicId, $expectedVersion, $reason,
            ): RentalRequest {
                $rental = RentalRequest::query()->withoutGlobalScopes()
                    ->where('organizer_tenant_id', $organizerTenantId)
                    ->where('public_id', $rentalPublicId)
                    ->lockForUpdate()
                    ->first();

                if ($rental === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_RENTAL_NOT_FOUND);
                }

                if ($rental->status === 'cancelled') {
                    return $rental;
                }

                $transitionedAt = now()->toDateTimeImmutable();
                $this->states->transition(
                    $rental->status,
                    'cancelled',
                    'organizer',
                    $reason,
                    $expectedVersion,
                    (int) $rental->version,
                    $transitionedAt,
                );

                $this->reservations->release($rental, 'rental_cancelled', $transitionedAt);

                $delegation = ControlDelegation::query()->withoutGlobalScopes()
                    ->where('tenant_id', $rental->tenant_id)
                    ->where('organizer_tenant_id', $organizerTenantId)
                    ->where('rental_request_id', $rental->id)
                    ->lockForUpdate()
                    ->first();
                if ($delegation !== null && $delegation->revoked_at === null) {
                    $delegation->forceFill([
                        'status' => 'revoked',
                        'revoked_at' => $transitionedAt,
                        'revoked_by_user_id' => $actorUserId,
                        'revocation_reason' => 'rental_cancelled',
                        'version' => ((int) $delegation->version) + 1,
                    ])->save();
                }

                $rental->forceFill([
                    'status' => 'cancelled',
                    'version' => ((int) $rental->version) + 1,
                    'decision_reason' => $reason,
                    'cancelled_at' => $transitionedAt,
                ])->save();

                return $rental->fresh(['assets']);
            },
            fn (RentalRequest $rental) => $this->writeAudit(
                $rental,
                $actorUserId,
                $correlationId,
            ),
        );
    }

    private function writeAudit(RentalRequest $rental, int $actorUserId, string $correlationId): void
    {
        foreach (['owner', 'organizer'] as $scope) {
            $this->audit->write(new MarketplaceAuditEvent(
                'rental.cancelled',
                $scope,
                'succeeded',
                $correlationId,
                $rental->public_id,
                ['status' => 'cancelled', 'version' => (int) $rental->version],
                ownerTenantId: (int) $rental->tenant_id,
                organizerTenantId: (int) $rental->organizer_tenant_id,
                actorUserId: $actorUserId,
                reasonCode: 'organizer_cancelled',
            ));
        }
    }
}
