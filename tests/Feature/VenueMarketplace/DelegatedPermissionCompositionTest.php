<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Authorization\Application\Contracts\DelegatedControlGuard;
use App\Modules\Authorization\Application\Contracts\DelegatedControlRequest;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class DelegatedPermissionCompositionTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_delegation_never_grants_a_base_permission(): void
    {
        $guard = app(DelegatedControlGuard::class);

        $decision = $guard->decide(new DelegatedControlRequest(
            organizerTenantId: 1,
            actorUserId: 2,
            eventId: 3,
            resourceModule: 'access_control',
            resourceType: 'turnstile',
            resourcePublicReference: (string) Str::ulid(),
            requestedCapability: 'acs.configure',
            now: new DateTimeImmutable('2027-01-10T10:00:00Z'),
            existingPermissionAllowed: false,
            delegationPublicId: (string) Str::ulid(),
        ));

        self::assertFalse($decision->allowed);
        self::assertSame(Phase6Problem::MARKETPLACE_PERMISSION_DENIED, $decision->reason);
    }

    public function test_delegation_with_base_permission_denied_still_denies(): void
    {
        $this->freezeMarketplaceClock();
        $guard = app(DelegatedControlGuard::class);

        $decision = $guard->decide(new DelegatedControlRequest(
            organizerTenantId: 1,
            actorUserId: 2,
            eventId: 3,
            resourceModule: 'kiosk',
            resourceType: 'kiosk',
            resourcePublicReference: (string) Str::ulid(),
            requestedCapability: 'kiosk.manage',
            now: new DateTimeImmutable('2027-01-10T10:00:00Z'),
            existingPermissionAllowed: false,
        ));

        self::assertFalse($decision->allowed);
        self::assertSame(Phase6Problem::MARKETPLACE_PERMISSION_DENIED, $decision->reason);
    }

    public function test_ordinary_operation_without_delegation_is_allowed_with_base_permission(): void
    {
        $guard = app(DelegatedControlGuard::class);

        $decision = $guard->decide(new DelegatedControlRequest(
            organizerTenantId: 1,
            actorUserId: 2,
            eventId: 3,
            resourceModule: 'scanning',
            resourceType: 'scanner',
            resourcePublicReference: (string) Str::ulid(),
            requestedCapability: 'checkin.scan.submit',
            now: new DateTimeImmutable('2027-01-10T10:00:00Z'),
            existingPermissionAllowed: true,
        ));

        self::assertTrue($decision->allowed);
    }

    public function test_nonexistent_delegation_does_not_grant_access(): void
    {
        $guard = app(DelegatedControlGuard::class);

        $decision = $guard->decide(new DelegatedControlRequest(
            organizerTenantId: 1,
            actorUserId: 2,
            eventId: 3,
            resourceModule: 'badge_printing',
            resourceType: 'printer',
            resourcePublicReference: (string) Str::ulid(),
            requestedCapability: 'badge.print',
            now: new DateTimeImmutable('2027-01-10T10:00:00Z'),
            existingPermissionAllowed: true,
            delegationPublicId: (string) Str::ulid(),
        ));

        self::assertFalse($decision->allowed);
        self::assertSame(Phase6Problem::MARKETPLACE_DELEGATION_NOT_FOUND, $decision->reason);
    }

    public function test_delegation_cannot_escalate_capability_beyond_granted_set(): void
    {
        $guard = app(DelegatedControlGuard::class);

        $decision = $guard->decide(new DelegatedControlRequest(
            organizerTenantId: 1,
            actorUserId: 2,
            eventId: 3,
            resourceModule: 'access_control',
            resourceType: 'turnstile',
            resourcePublicReference: (string) Str::ulid(),
            requestedCapability: 'acs.admin.override',
            now: new DateTimeImmutable('2027-01-10T10:00:00Z'),
            existingPermissionAllowed: true,
            delegationPublicId: (string) Str::ulid(),
        ));

        self::assertFalse($decision->allowed);
    }
}
