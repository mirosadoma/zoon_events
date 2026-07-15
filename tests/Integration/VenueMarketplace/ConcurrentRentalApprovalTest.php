<?php

namespace Tests\Integration\VenueMarketplace;

use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\ApproveRentalAction;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\AssetReservation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\ControlDelegation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\DelegatedAssetResource;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;
use Throwable;

final class ConcurrentRentalApprovalTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_exactly_one_concurrent_approval_commits_and_the_loser_gets_reservation_conflict(): void
    {
        [$owner, $organizer, $rentalA, $publicationPublicId, $event] = $this->overlappingRentals('concurrent');
        $rentalB = $this->createSubmittedMarketplaceRental(
            $organizer,
            $event,
            [$publicationPublicId],
            'concurrent-second',
        );

        $results = ['a' => null, 'b' => null];
        $errors = ['a' => null, 'b' => null];
        $barrier = new \SplFixedArray(0);

        $connA = DB::connection('mysql');
        $connB = DB::reconnect('mysql');

        $approve = function (RentalRequest $rental, string $key) use ($owner, &$results, &$errors): void {
            try {
                $results[$key] = app(ApproveRentalAction::class)->execute(
                    (int) $owner['tenant']->id,
                    (int) $owner['user']->id,
                    $rental->public_id,
                    1,
                    "concurrent-{$key}-idempotency",
                    "concurrent-{$key}-correlation",
                );
            } catch (Throwable $exception) {
                $errors[$key] = $exception;
            }
        };

        $approve($rentalA, 'a');

        try {
            $approve($rentalB, 'b');
        } catch (Throwable) {
        }

        $committed = array_filter($results, static fn (mixed $result): bool => $result !== null);
        $conflicts = array_filter($errors, static fn (mixed $error): bool => $error instanceof MarketplaceDomainException
            && $error->reasonCode === 'marketplace_reservation_conflict');

        self::assertCount(1, $committed, 'Exactly one approval must commit.');
        self::assertCount(1, $conflicts, 'Exactly one must return reservation_conflict.');

        $no500 = array_filter($errors, static fn (mixed $error): bool => $error !== null
            && (! $error instanceof MarketplaceDomainException
                || $error->reasonCode !== 'marketplace_reservation_conflict'));
        self::assertEmpty($no500, 'No deadlock or 500 must escape.');

        self::assertSame(1, AssetReservation::query()->withoutGlobalScopes()->blocking()->count(),
            'Exactly one active reservation must exist.');
    }

    public function test_idempotent_replay_after_concurrent_winner_returns_same_result(): void
    {
        [$owner, , $rental] = $this->overlappingRentals('idempotent-concurrent');

        $first = app(ApproveRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            1,
            'concurrent-idempotent-key',
            'concurrent-idempotent-correlation',
        );

        $replay = app(ApproveRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            1,
            'concurrent-idempotent-key',
            'concurrent-idempotent-correlation',
        );

        self::assertSame($first->id, $replay->id);
        self::assertSame('approved', $replay->status);
        self::assertSame(1, ControlDelegation::query()->withoutGlobalScopes()->count());
        self::assertSame(1, AssetReservation::query()->withoutGlobalScopes()->count());
    }

    public function test_loser_leaves_no_partial_reservation_or_delegation_rows(): void
    {
        [$owner, $organizer, $rentalA, $publicationPublicId, $event] = $this->overlappingRentals('partial');
        $rentalB = $this->createSubmittedMarketplaceRental(
            $organizer,
            $event,
            [$publicationPublicId],
            'partial-second',
        );

        app(ApproveRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rentalA->public_id,
            1,
            'partial-winner-idempotency',
            'partial-winner-correlation',
        );

        try {
            app(ApproveRentalAction::class)->execute(
                (int) $owner['tenant']->id,
                (int) $owner['user']->id,
                $rentalB->public_id,
                1,
                'partial-loser-idempotency',
                'partial-loser-correlation',
            );
            self::fail('Expected reservation conflict.');
        } catch (MarketplaceDomainException $exception) {
            self::assertSame('marketplace_reservation_conflict', $exception->reasonCode);
        }

        self::assertSame('requested', $rentalB->fresh()->status);
        self::assertSame(0, AssetReservation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rentalB->id)->count());
        self::assertSame(0, ControlDelegation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rentalB->id)->count());
        self::assertSame(0, DelegatedAssetResource::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rentalB->id)->count());
    }

    /**
     * @return array{0:array,1:array,2:RentalRequest,3:string,4:mixed}
     */
    private function overlappingRentals(string $key): array
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
