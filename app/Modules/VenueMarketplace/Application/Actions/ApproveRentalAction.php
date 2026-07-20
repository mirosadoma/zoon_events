<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Tenancy\Application\Contracts\OrganizationEligibility;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\RentalStateMachine;
use App\Modules\VenueMarketplace\Domain\Services\ReservationConflictDetector;
use App\Modules\VenueMarketplace\Domain\ValueObjects\RentalWindow;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Audit\DatabaseMarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\AssetAvailabilityWindow;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\AssetReservation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\ControlDelegation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\DelegatedAssetResource;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceCatalogPublication;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\VenueAsset;
use Illuminate\Support\Str;

final readonly class ApproveRentalAction
{
    public function __construct(
        private AuditedTransaction $transactions,
        private OrganizationEligibility $eligibility,
        private RentalStateMachine $states,
        private ReservationConflictDetector $conflicts,
        private DatabaseMarketplaceAuditWriter $audit,
    ) {}

    public function execute(
        int $ownerTenantId,
        int $actorUserId,
        string $rentalPublicId,
        int $expectedVersion,
        string $idempotencyKey,
        string $correlationId,
    ): RentalRequest {
        if (! $this->eligibility->check($ownerTenantId, OrganizationEligibility::OWN_VENUES)->eligible) {
            throw new MarketplaceDomainException(Phase6Problem::ORGANIZATION_TYPE_NOT_ELIGIBLE);
        }

        $keyHash = hash('sha256', $idempotencyKey);

        return $this->transactions->run(
            function () use (
                $ownerTenantId, $actorUserId, $rentalPublicId, $expectedVersion, $keyHash,
            ): RentalRequest {
                $rental = RentalRequest::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $ownerTenantId)
                    ->where('public_id', $rentalPublicId)
                    ->with('assets')
                    ->lockForUpdate()
                    ->first();

                if ($rental === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_RENTAL_NOT_FOUND);
                }

                if ($rental->status === 'approved') {
                    $replay = ControlDelegation::query()->withoutGlobalScopes()
                        ->where('tenant_id', $ownerTenantId)
                        ->where('rental_request_id', $rental->id)
                        ->where('idempotency_key_hash', $keyHash)
                        ->exists();

                    if ($replay) {
                        return $rental;
                    }
                }

                $transitionedAt = now()->toDateTimeImmutable();
                $this->states->transition(
                    $rental->status,
                    'approved',
                    'owner',
                    null,
                    $expectedVersion,
                    (int) $rental->version,
                    $transitionedAt,
                );

                $assetIds = $this->conflicts->orderedAssetIds(
                    $rental->assets->pluck('venue_asset_id')->map(fn ($id): int => (int) $id)->all(),
                );
                $assets = VenueAsset::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $ownerTenantId)
                    ->whereIn('id', $assetIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($assets->count() !== count($assetIds) || $assets->contains(
                    fn (VenueAsset $asset): bool => ! $asset->isActive(),
                )) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_UNAVAILABLE);
                }

                $this->assertPublicationsAndAvailability($rental, $assetIds);
                $this->assertNoConflict($rental, $assetIds);

                foreach ($rental->assets->sortBy('venue_asset_id') as $line) {
                    AssetReservation::query()->forceCreate([
                        'tenant_id' => $ownerTenantId,
                        'organizer_tenant_id' => $rental->organizer_tenant_id,
                        'rental_request_id' => $rental->id,
                        'rental_asset_id' => $line->id,
                        'venue_asset_id' => $line->venue_asset_id,
                        'reserved_from' => $rental->requested_start_at,
                        'reserved_until' => $rental->requested_end_at,
                        'status' => 'reserved',
                    ]);
                }

                $delegation = ControlDelegation::query()->forceCreate([
                    'tenant_id' => $ownerTenantId,
                    'organizer_tenant_id' => $rental->organizer_tenant_id,
                    'public_id' => (string) Str::ulid(),
                    'rental_request_id' => $rental->id,
                    'event_id' => $rental->event_id,
                    'status' => 'pending',
                    'starts_at' => $rental->requested_start_at,
                    'ends_at' => $rental->requested_end_at,
                    'version' => 1,
                    'idempotency_key_hash' => $keyHash,
                ]);

                foreach ($rental->assets->sortBy('venue_asset_id') as $line) {
                    [$module, $status] = $this->resourceDefaults($line->asset_type);
                    DelegatedAssetResource::query()->forceCreate([
                        'tenant_id' => $ownerTenantId,
                        'organizer_tenant_id' => $rental->organizer_tenant_id,
                        'control_delegation_id' => $delegation->id,
                        'rental_request_id' => $rental->id,
                        'rental_asset_id' => $line->id,
                        'venue_asset_id' => $line->venue_asset_id,
                        'resource_module' => $module,
                        'resource_type' => $line->asset_type,
                        'granted_capabilities' => $line->selected_capabilities,
                        'provisioning_status' => $status,
                        'idempotency_key_hash' => hash('sha256', $keyHash.':'.$line->id),
                    ]);
                }

                $rental->forceFill([
                    'status' => 'approved',
                    'version' => ((int) $rental->version) + 1,
                    'owner_decided_by_user_id' => $actorUserId,
                    'decision_reason' => null,
                    'approved_at' => $transitionedAt,
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

    /** @param list<int> $assetIds */
    private function assertPublicationsAndAvailability(RentalRequest $rental, array $assetIds): void
    {
        $activePublicationIds = MarketplaceCatalogPublication::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $rental->tenant_id)
            ->where('status', 'active')
            ->whereIn('id', $rental->assets->pluck('catalog_publication_id'))
            ->get()
            ->keyBy('id');

        foreach ($rental->assets as $line) {
            $publication = $activePublicationIds->get($line->catalog_publication_id);
            if ($publication === null
                || (int) $publication->publication_version !== (int) $line->publication_version) {
                throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_UNAVAILABLE);
            }
        }

        $availableAssetIds = AssetAvailabilityWindow::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $rental->tenant_id)
            ->whereIn('venue_asset_id', $assetIds)
            ->where('status', 'available')
            ->where('available_from', '<=', $rental->requested_start_at)
            ->where('available_until', '>=', $rental->requested_end_at)
            ->pluck('venue_asset_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();

        if (count($availableAssetIds) !== count($assetIds)) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_UNAVAILABLE);
        }
    }

    /** @param list<int> $assetIds */
    private function assertNoConflict(RentalRequest $rental, array $assetIds): void
    {
        $reservations = AssetReservation::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $rental->tenant_id)
            ->whereIn('venue_asset_id', $assetIds)
            ->blocking()
            ->where('reserved_from', '<', $rental->requested_end_at)
            ->where('reserved_until', '>', $rental->requested_start_at)
            ->with(['rental:id,public_id', 'venueAsset:id,public_id'])
            ->get()
            ->map(fn (AssetReservation $reservation): array => [
                'venue_asset_id' => (int) $reservation->venue_asset_id,
                'asset_public_id' => $reservation->venueAsset->public_id,
                'rental_public_id' => $reservation->rental->public_id,
                'status' => $reservation->status,
                'reserved_from' => $reservation->reserved_from,
                'reserved_until' => $reservation->reserved_until,
            ])
            ->all();

        $conflicts = $this->conflicts->findConflicts(
            new RentalWindow(
                $rental->requested_start_at->toDateTimeImmutable(),
                $rental->requested_end_at->toDateTimeImmutable(),
            ),
            $reservations,
            $assetIds,
        );

        if ($conflicts !== []) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_RESERVATION_CONFLICT);
        }
    }

    /** @return array{string, string} */
    private function resourceDefaults(string $assetType): array
    {
        return match ($assetType) {
            'turnstile', 'security_gate', 'access_lane', 'access_zone' => ['access_control', 'pending'],
            'kiosk' => ['kiosk', 'pending'],
            'printer' => ['badge_printing', 'pending'],
            'scanner' => ['scanning', 'pending'],
            'camera' => ['catalog_only', 'not_applicable'],
            default => throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_UNAVAILABLE),
        };
    }

    private function writeAudit(RentalRequest $rental, int $actorUserId, string $correlationId): void
    {
        foreach (['owner', 'organizer'] as $scope) {
            $this->audit->write(new MarketplaceAuditEvent(
                'rental.approved',
                $scope,
                'succeeded',
                $correlationId,
                $rental->public_id,
                ['status' => 'approved', 'version' => (int) $rental->version],
                ownerTenantId: (int) $rental->tenant_id,
                organizerTenantId: (int) $rental->organizer_tenant_id,
                actorUserId: $actorUserId,
            ));
        }
    }
}
