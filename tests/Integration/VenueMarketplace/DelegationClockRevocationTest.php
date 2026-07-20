<?php

namespace Tests\Integration\VenueMarketplace;

use App\Modules\Authorization\Application\Contracts\DelegatedControlGuard;
use App\Modules\Authorization\Application\Contracts\DelegatedControlRequest;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\ActivateRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\ApproveRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\ExpireRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\RevokeRentalAction;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\ControlDelegation;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class DelegationClockRevocationTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_one_instant_before_start_delegation_is_not_active(): void
    {
        [$owner, $organizer, $rental] = $this->approvedRental('before-start');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T05:59:59Z'));

        $result = app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'before-start-corr',
        );

        self::assertSame('approved', $result->status);
    }

    public function test_exact_start_activates_delegation(): void
    {
        [$owner, $organizer, $rental] = $this->approvedRental('exact-start');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T06:00:00Z'));

        $result = app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'exact-start-corr',
        );

        self::assertSame('active', $result->status);
    }

    public function test_exact_end_expires_active_rental_and_releases_delegation(): void
    {
        [$owner, $organizer, $rental] = $this->activatedRental('exact-end');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T08:00:00Z'));

        $result = app(ExpireRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'exact-end-corr',
        );

        self::assertSame('completed', $result->status);

        $delegation = ControlDelegation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->first();

        self::assertSame('expired', $delegation->status);
        self::assertNotNull($delegation->expired_at);
    }

    public function test_owner_revocation_denies_delegated_operation(): void
    {
        [$owner, $organizer, $rental] = $this->activatedRental('revoke-race');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T07:00:00Z'));

        app(RevokeRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            'Safety concern',
            (int) $rental->version,
            'revoke-race-corr',
        );

        $delegation = ControlDelegation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->first();

        $guard = app(DelegatedControlGuard::class);
        $decision = $guard->decide(new DelegatedControlRequest(
            organizerTenantId: (int) $organizer['tenant']->id,
            actorUserId: (int) $organizer['user']->id,
            eventId: (int) $delegation->event_id,
            resourceModule: 'kiosk',
            resourceType: 'kiosk',
            resourcePublicReference: (string) Str::ulid(),
            requestedCapability: 'kiosk.manage',
            now: new DateTimeImmutable('2027-01-10T07:01:00Z'),
            existingPermissionAllowed: true,
            delegationPublicId: $delegation->public_id,
        ));

        self::assertFalse($decision->allowed);
        self::assertSame(Phase6Problem::MARKETPLACE_DELEGATION_REVOKED, $decision->reason);
    }

    public function test_late_release_after_expiry_is_idempotent(): void
    {
        [$owner, $organizer, $rental] = $this->activatedRental('late-release');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T08:00:00Z'));

        app(ExpireRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'late-release-corr-1',
        );

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T09:00:00Z'));

        $result = app(ExpireRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'late-release-corr-2',
        );

        self::assertSame('completed', $result->status);
        self::assertSame(1, ControlDelegation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->count());
    }

    /**
     * @return array{0:array,1:array,2:mixed}
     */
    private function approvedRental(string $key): array
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

        $rental = app(ApproveRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            1,
            "{$key}-approve-idem",
            "{$key}-approve-corr",
        );

        return [$owner, $organizer, $rental];
    }

    /**
     * @return array{0:array,1:array,2:mixed}
     */
    private function activatedRental(string $key): array
    {
        [$owner, $organizer, $rental] = $this->approvedRental($key);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T06:30:00Z'));

        $rental = app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            "{$key}-activate-corr",
        );

        return [$owner, $organizer, $rental];
    }
}
