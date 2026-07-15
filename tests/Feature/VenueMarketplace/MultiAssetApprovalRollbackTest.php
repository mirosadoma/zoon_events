<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\ApproveRentalAction;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\AssetReservation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\ControlDelegation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\DelegatedAssetResource;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceCatalogPublication;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\VenueAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class MultiAssetApprovalRollbackTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_one_conflicting_line_among_several_rolls_back_all_reservations(): void
    {
        [$owner, $organizer, $rentalA, $publicationPublicIds, $event] = $this->multiAssetSetup('conflict-line');

        app(ApproveRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rentalA->public_id,
            1,
            'conflict-line-first-idempotency',
            'conflict-line-first-correlation',
        );

        $rentalB = $this->createSubmittedMarketplaceRental(
            $organizer,
            $event,
            $publicationPublicIds,
            'conflict-line-second',
        );

        $this->assertApprovalRollback(
            $owner,
            $rentalB,
            Phase6Problem::MARKETPLACE_RESERVATION_CONFLICT,
        );
    }

    public function test_stale_expected_version_rolls_back_without_side_effects(): void
    {
        [$owner, , $rental] = $this->multiAssetSetup('stale-version');

        $rental->forceFill(['version' => 2])->save();

        $this->assertApprovalRollback(
            $owner,
            $rental,
            Phase6Problem::MARKETPLACE_RENTAL_STATE_CONFLICT,
        );

        self::assertSame(2, (int) $rental->fresh()->version, 'Version must not change on stale conflict.');
    }

    public function test_inactive_publication_rolls_back_reservations_and_delegation(): void
    {
        [$owner, , $rental] = $this->multiAssetSetup('inactive-pub');

        MarketplaceCatalogPublication::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $owner['tenant']->id)
            ->where('status', 'active')
            ->limit(1)
            ->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);

        $this->assertApprovalRollback(
            $owner,
            $rental,
            Phase6Problem::MARKETPLACE_ASSET_UNAVAILABLE,
        );
    }

    public function test_retired_asset_rolls_back_without_partial_rows(): void
    {
        [$owner, , $rental] = $this->multiAssetSetup('retired-asset');

        VenueAsset::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $owner['tenant']->id)
            ->limit(1)
            ->update(['operational_status' => 'retired', 'retired_at' => now()]);

        $this->assertApprovalRollback(
            $owner,
            $rental,
            Phase6Problem::MARKETPLACE_ASSET_UNAVAILABLE,
        );
    }

    public function test_deterministic_sorted_lock_ordering_prevents_deadlock_in_multi_asset_approval(): void
    {
        [$owner, , $rental] = $this->multiAssetSetup('sorted-locks');

        $assetIds = $rental->assets->pluck('venue_asset_id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        self::assertSame($assetIds, array_values(array_unique($assetIds, SORT_NUMERIC)),
            'Asset IDs must already be sorted for deterministic lock ordering.');

        $approved = app(ApproveRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            1,
            'sorted-locks-idempotency',
            'sorted-locks-correlation',
        );

        self::assertSame('approved', $approved->status);
        self::assertSame(
            count($assetIds),
            AssetReservation::query()->withoutGlobalScopes()
                ->where('rental_request_id', $rental->id)->count(),
        );
    }

    private function assertApprovalRollback(array $owner, RentalRequest $rental, string $expectedReasonCode): void
    {
        try {
            app(ApproveRentalAction::class)->execute(
                (int) $owner['tenant']->id,
                (int) $owner['user']->id,
                $rental->public_id,
                (int) $rental->version,
                "rollback-{$rental->public_id}-idempotency",
                "rollback-{$rental->public_id}-correlation",
            );
            self::fail("Expected approval to fail with {$expectedReasonCode}.");
        } catch (MarketplaceDomainException $exception) {
            self::assertSame($expectedReasonCode, $exception->reasonCode);
        }

        self::assertSame('requested', $rental->fresh()->status, 'Status must remain requested after rollback.');
        self::assertSame(0, AssetReservation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->count(),
            'No reservations must remain after rollback.');
        self::assertSame(0, ControlDelegation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->count(),
            'No delegation must remain after rollback.');
        self::assertSame(0, DelegatedAssetResource::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->count(),
            'No delegated resources must remain after rollback.');
    }

    /**
     * @return array{0:array,1:array,2:RentalRequest,3:list<string>,4:mixed}
     */
    private function multiAssetSetup(string $key): array
    {
        $this->freezeMarketplaceClock();
        $organizer = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        $event = $this->createMarketplaceEvent($organizer['tenant'], $organizer['user']);
        $owner = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $inventory = $this->createPublishedMarketplaceInventory($owner, $key);
        app(TenantContextStore::class)->clear();

        $publicationPublicIds = collect($inventory['assets'])
            ->flatMap(fn ($asset) => $asset->publications()
                ->where('status', 'active')
                ->pluck('public_id'))
            ->values()
            ->all();

        self::assertGreaterThanOrEqual(2, count($publicationPublicIds),
            'Multi-asset tests require at least 2 published assets.');

        $rental = $this->createSubmittedMarketplaceRental(
            $organizer,
            $event,
            $publicationPublicIds,
            "{$key}-rental",
        );

        return [$owner, $organizer, $rental, $publicationPublicIds, $event];
    }
}
