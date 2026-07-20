<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\ApproveRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\CancelRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\RejectRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\RevokeRentalAction;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\AssetReservation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\ControlDelegation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\DelegatedAssetResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class RentalDecisionLifecycleTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_approve_is_atomic_idempotent_and_creates_pending_control_rows(): void
    {
        [$owner, $organizer, $rental] = $this->submittedRental('approve');

        $approved = app(ApproveRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            1,
            'approve-rental-idempotency',
            'approve-rental-correlation',
        );
        $replay = app(ApproveRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            1,
            'approve-rental-idempotency',
            'approve-rental-correlation',
        );

        self::assertSame('approved', $approved->status);
        self::assertSame(2, $approved->version);
        self::assertSame($approved->id, $replay->id);
        self::assertSame(1, AssetReservation::query()->withoutGlobalScopes()->count());
        self::assertSame(1, ControlDelegation::query()->withoutGlobalScopes()->count());
        self::assertSame(1, DelegatedAssetResource::query()->withoutGlobalScopes()->count());
        self::assertSame('pending', ControlDelegation::query()->withoutGlobalScopes()->value('status'));
    }

    public function test_overlapping_approval_returns_safe_conflict_without_partial_rows(): void
    {
        [$owner, $organizer, $first, $publicationPublicId, $event] = $this->submittedRental('conflict');
        $second = $this->createSubmittedMarketplaceRental(
            $organizer,
            $event,
            [$publicationPublicId],
            'conflict-second',
        );

        app(ApproveRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $first->public_id,
            1,
            'conflict-first-idempotency',
            'conflict-first-correlation',
        );

        try {
            app(ApproveRentalAction::class)->execute(
                (int) $owner['tenant']->id,
                (int) $owner['user']->id,
                $second->public_id,
                1,
                'conflict-second-idempotency',
                'conflict-second-correlation',
            );
            self::fail('Expected the overlapping approval to be denied.');
        } catch (MarketplaceDomainException $exception) {
            self::assertSame('marketplace_reservation_conflict', $exception->reasonCode);
        }

        self::assertSame('requested', $second->fresh()->status);
        self::assertSame(1, AssetReservation::query()->withoutGlobalScopes()->count());
        self::assertSame(1, ControlDelegation::query()->withoutGlobalScopes()->count());
        self::assertSame(1, DelegatedAssetResource::query()->withoutGlobalScopes()->count());
    }

    public function test_reject_cancel_and_revoke_apply_versioned_terminal_rules_and_release_only_their_rows(): void
    {
        [$owner, $organizer, $rejected] = $this->submittedRental('reject');
        $rejected = app(RejectRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rejected->public_id,
            'Not operationally available',
            1,
            'reject-correlation',
        );
        self::assertSame('rejected', $rejected->status);
        self::assertSame(2, $rejected->version);

        [, $cancelOrganizer, $cancelled] = $this->submittedRental('cancel');
        $cancelled = app(CancelRentalAction::class)->execute(
            (int) $cancelOrganizer['tenant']->id,
            (int) $cancelOrganizer['user']->id,
            $cancelled->public_id,
            1,
            'cancel-correlation',
        );
        self::assertSame('cancelled', $cancelled->status);
        self::assertSame(0, AssetReservation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $cancelled->id)->count());

        [$revokeOwner, , $revoked] = $this->submittedRental('revoke');
        $revoked = app(ApproveRentalAction::class)->execute(
            (int) $revokeOwner['tenant']->id,
            (int) $revokeOwner['user']->id,
            $revoked->public_id,
            1,
            'revoke-approve-idempotency',
            'revoke-approve-correlation',
        );
        $revoked = app(RevokeRentalAction::class)->execute(
            (int) $revokeOwner['tenant']->id,
            (int) $revokeOwner['user']->id,
            $revoked->public_id,
            'Safety shutdown',
            2,
            'revoke-correlation',
        );

        self::assertSame('revoked', $revoked->status);
        self::assertNotNull($revoked->revoked_at);
        self::assertSame('released', AssetReservation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $revoked->id)->value('status'));
        self::assertSame('revoked', ControlDelegation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $revoked->id)->value('status'));
    }

    /**
     * @return array{0:array,1:array,2:mixed,3:string,4:mixed}
     */
    private function submittedRental(string $key): array
    {
        $this->freezeMarketplaceClock();
        $organizer = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        $event = $this->createMarketplaceEvent($organizer['tenant'], $organizer['user']);
        $owner = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $inventory = $this->createPublishedMarketplaceInventory($owner, $key);
        app(TenantContextStore::class)->clear();
        $publicationPublicId = $inventory['assets'][3]->publications()
            ->where('status', 'active')
            ->value('public_id');
        $rental = $this->createSubmittedMarketplaceRental(
            $organizer,
            $event,
            [$publicationPublicId],
            "{$key}-rental",
        );

        return [$owner, $organizer, $rental, $publicationPublicId, $event];
    }
}
