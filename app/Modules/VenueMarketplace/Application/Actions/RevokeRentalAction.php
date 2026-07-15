<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Tenancy\Application\Contracts\OrganizationEligibility;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Jobs\ReleaseMarketplaceDelegation;
use App\Modules\VenueMarketplace\Application\Services\ReservationReleaseService;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\RentalStateMachine;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Audit\DatabaseMarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\ControlDelegation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;

final readonly class RevokeRentalAction
{
    public function __construct(
        private AuditedTransaction $transactions,
        private OrganizationEligibility $eligibility,
        private RentalStateMachine $states,
        private ReservationReleaseService $reservations,
        private DatabaseMarketplaceAuditWriter $audit,
    ) {}

    public function execute(
        int $ownerTenantId,
        int $actorUserId,
        string $rentalPublicId,
        string $reason,
        int $expectedVersion,
        string $correlationId,
    ): RentalRequest {
        if (! $this->eligibility->check($ownerTenantId, OrganizationEligibility::OWN_VENUES)->eligible) {
            throw new MarketplaceDomainException(Phase6Problem::ORGANIZATION_TYPE_NOT_ELIGIBLE);
        }

        $reason = trim($reason);

        return $this->transactions->run(
            function () use (
                $ownerTenantId, $actorUserId, $rentalPublicId, $reason, $expectedVersion,
            ): RentalRequest {
                $rental = RentalRequest::query()->withoutGlobalScopes()
                    ->where('tenant_id', $ownerTenantId)
                    ->where('public_id', $rentalPublicId)
                    ->lockForUpdate()
                    ->first();

                if ($rental === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_RENTAL_NOT_FOUND);
                }

                if ($rental->status === 'revoked'
                    && hash_equals((string) $rental->decision_reason, $reason)) {
                    return $rental;
                }

                $transitionedAt = now()->toDateTimeImmutable();
                $this->states->transition(
                    $rental->status,
                    'revoked',
                    'owner',
                    $reason,
                    $expectedVersion,
                    (int) $rental->version,
                    $transitionedAt,
                );

                $delegation = ControlDelegation::query()->withoutGlobalScopes()
                    ->where('tenant_id', $ownerTenantId)
                    ->where('organizer_tenant_id', $rental->organizer_tenant_id)
                    ->where('rental_request_id', $rental->id)
                    ->lockForUpdate()
                    ->first();

                if ($delegation !== null && $delegation->revoked_at === null) {
                    $delegation->forceFill([
                        'status' => 'revoked',
                        'revoked_at' => $transitionedAt,
                        'revoked_by_user_id' => $actorUserId,
                        'revocation_reason' => $reason,
                        'version' => ((int) $delegation->version) + 1,
                    ])->save();
                }

                $this->reservations->release($rental, 'rental_revoked', $transitionedAt);

                $rental->forceFill([
                    'status' => 'revoked',
                    'version' => ((int) $rental->version) + 1,
                    'owner_decided_by_user_id' => $actorUserId,
                    'decision_reason' => $reason,
                    'revoked_at' => $transitionedAt,
                ])->save();

                return $rental->fresh(['assets']);
            },
            function (RentalRequest $rental) use ($actorUserId, $correlationId): void {
                $this->writeAudit($rental, $actorUserId, $correlationId);
                $this->dispatchRelease($rental, $correlationId);
            },
        );
    }

    private function dispatchRelease(RentalRequest $rental, string $correlationId): void
    {
        $delegation = ControlDelegation::query()->withoutGlobalScopes()
            ->where('tenant_id', $rental->tenant_id)
            ->where('rental_request_id', $rental->id)
            ->first();

        if ($delegation !== null && in_array($delegation->status, ['active', 'degraded', 'pending'], true)) {
            ReleaseMarketplaceDelegation::dispatch(
                (int) $rental->tenant_id,
                $delegation->public_id,
                $correlationId,
            );
        }
    }

    private function writeAudit(RentalRequest $rental, int $actorUserId, string $correlationId): void
    {
        foreach (['owner', 'organizer'] as $scope) {
            $this->audit->write(new MarketplaceAuditEvent(
                'rental.revoked',
                $scope,
                'succeeded',
                $correlationId,
                $rental->public_id,
                ['status' => 'revoked', 'version' => (int) $rental->version],
                ownerTenantId: (int) $rental->tenant_id,
                organizerTenantId: (int) $rental->organizer_tenant_id,
                actorUserId: $actorUserId,
                reasonCode: 'owner_revoked',
            ));
        }
    }
}
