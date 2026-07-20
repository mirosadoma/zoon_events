<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Tenancy\Application\Contracts\OrganizationEligibility;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\RentalStateMachine;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Audit\DatabaseMarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;

final readonly class RejectRentalAction
{
    public function __construct(
        private AuditedTransaction $transactions,
        private OrganizationEligibility $eligibility,
        private RentalStateMachine $states,
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

                if ($rental->status === 'rejected' && hash_equals((string) $rental->decision_reason, $reason)) {
                    return $rental;
                }

                $transitionedAt = now()->toDateTimeImmutable();
                $this->states->transition(
                    $rental->status,
                    'rejected',
                    'owner',
                    $reason,
                    $expectedVersion,
                    (int) $rental->version,
                    $transitionedAt,
                );

                $rental->forceFill([
                    'status' => 'rejected',
                    'version' => ((int) $rental->version) + 1,
                    'owner_decided_by_user_id' => $actorUserId,
                    'decision_reason' => $reason,
                    'rejected_at' => $transitionedAt,
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
                'rental.rejected',
                $scope,
                'succeeded',
                $correlationId,
                $rental->public_id,
                ['status' => 'rejected', 'version' => (int) $rental->version],
                ownerTenantId: (int) $rental->tenant_id,
                organizerTenantId: (int) $rental->organizer_tenant_id,
                actorUserId: $actorUserId,
                reasonCode: 'owner_rejected',
            ));
        }
    }
}
