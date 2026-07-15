<?php

namespace Tests\Unit\Authorization;

use App\Modules\Authorization\Application\Contracts\DelegatedControlDecision;
use App\Modules\Authorization\Application\Contracts\DelegatedControlRequest;
use App\Modules\VenueMarketplace\Application\Authorization\DatabaseDelegatedControlGuard;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\ControlDelegation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\DelegatedAssetResource;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DelegatedControlGuardMatrixTest extends TestCase
{
    use RefreshDatabase;
    private DatabaseDelegatedControlGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new DatabaseDelegatedControlGuard;
    }

    public function test_ordinary_operation_without_delegation_id_is_allowed_when_base_permission_passes(): void
    {
        $request = $this->makeRequest(existingPermissionAllowed: true, delegationPublicId: null);

        $decision = $this->guard->decide($request);

        self::assertTrue($decision->allowed);
        self::assertNull($decision->reason);
    }

    public function test_ordinary_operation_without_delegation_id_is_denied_when_base_permission_fails(): void
    {
        $request = $this->makeRequest(existingPermissionAllowed: false, delegationPublicId: null);

        $decision = $this->guard->decide($request);

        self::assertFalse($decision->allowed);
        self::assertSame(Phase6Problem::MARKETPLACE_PERMISSION_DENIED, $decision->reason);
    }

    public function test_delegated_request_denied_when_base_permission_fails_regardless_of_delegation(): void
    {
        $request = $this->makeRequest(existingPermissionAllowed: false, delegationPublicId: (string) Str::ulid());

        $decision = $this->guard->decide($request);

        self::assertFalse($decision->allowed);
        self::assertSame(Phase6Problem::MARKETPLACE_PERMISSION_DENIED, $decision->reason);
    }

    public function test_delegated_request_denied_when_delegation_not_found(): void
    {
        $request = $this->makeRequest(existingPermissionAllowed: true, delegationPublicId: (string) Str::ulid());

        $decision = $this->guard->decide($request);

        self::assertFalse($decision->allowed);
        self::assertSame(Phase6Problem::MARKETPLACE_DELEGATION_NOT_FOUND, $decision->reason);
    }

    public function test_forged_opaque_delegation_id_returns_not_found(): void
    {
        $forgedId = 'FORGED_' . bin2hex(random_bytes(8));
        $request = $this->makeRequest(existingPermissionAllowed: true, delegationPublicId: $forgedId);

        $decision = $this->guard->decide($request);

        self::assertFalse($decision->allowed);
        self::assertSame(Phase6Problem::MARKETPLACE_DELEGATION_NOT_FOUND, $decision->reason);
    }

    #[DataProvider('denialReasonMatrix')]
    public function test_guard_denial_reasons_cover_full_matrix(
        string $expectedReason,
        string $delegationStatus,
        ?string $revokedAt,
        ?string $now,
        ?string $startsAt,
        ?string $endsAt,
        ?int $eventId,
        int $requestEventId,
        ?string $resourceModule,
        ?string $resourceType,
        ?string $resourceRef,
        ?string $capability,
        string $requestCapability,
    ): void {
        $publicId = (string) Str::ulid();
        $organizerTenantId = 1;

        $delegation = new ControlDelegation;
        $delegation->forceFill([
            'id' => 1,
            'tenant_id' => 10,
            'organizer_tenant_id' => $organizerTenantId,
            'rental_request_id' => 1,
            'event_id' => $eventId ?? $requestEventId,
            'public_id' => $publicId,
            'status' => $delegationStatus,
            'starts_at' => $startsAt ? new DateTimeImmutable($startsAt) : new DateTimeImmutable('2027-01-10T06:00:00Z'),
            'ends_at' => $endsAt ? new DateTimeImmutable($endsAt) : new DateTimeImmutable('2027-01-10T18:00:00Z'),
            'revoked_at' => $revokedAt ? new DateTimeImmutable($revokedAt) : null,
            'version' => 1,
        ]);
        $delegation->exists = true;
        $delegation->save();

        if ($resourceModule !== null) {
            $resource = new DelegatedAssetResource;
            $resource->forceFill([
                'control_delegation_id' => $delegation->id,
                'tenant_id' => 10,
                'organizer_tenant_id' => $organizerTenantId,
                'rental_request_id' => 1,
                'rental_asset_id' => 1,
                'venue_asset_id' => 1,
                'resource_module' => $resourceModule,
                'resource_type' => $resourceType ?? 'turnstile',
                'resource_public_reference' => $resourceRef ?? 'ref-1',
                'granted_capabilities' => $capability !== null ? [$capability] : [],
                'provisioning_status' => 'provisioned',
                'idempotency_key_hash' => hash('sha256', 'idem'),
            ]);
            $resource->exists = true;
            $resource->save();
        }

        $request = new DelegatedControlRequest(
            organizerTenantId: $organizerTenantId,
            actorUserId: 2,
            eventId: $requestEventId,
            resourceModule: $resourceModule ?? 'access_control',
            resourceType: $resourceType ?? 'turnstile',
            resourcePublicReference: $resourceRef ?? 'ref-1',
            requestedCapability: $requestCapability,
            now: new DateTimeImmutable($now ?? '2027-01-10T10:00:00Z'),
            existingPermissionAllowed: true,
            delegationPublicId: $publicId,
        );

        $decision = $this->guard->decide($request);

        self::assertFalse($decision->allowed);
        self::assertSame($expectedReason, $decision->reason);
    }

    public static function denialReasonMatrix(): array
    {
        return [
            'revoked delegation' => [
                Phase6Problem::MARKETPLACE_DELEGATION_REVOKED,
                'active', '2027-01-10T07:00:00Z', '2027-01-10T10:00:00Z',
                '2027-01-10T06:00:00Z', '2027-01-10T18:00:00Z',
                100, 100,
                'access_control', 'turnstile', 'ref-1', 'acs.configure', 'acs.configure',
            ],
            'revoked status without revoked_at' => [
                Phase6Problem::MARKETPLACE_DELEGATION_REVOKED,
                'revoked', null, '2027-01-10T10:00:00Z',
                '2027-01-10T06:00:00Z', '2027-01-10T18:00:00Z',
                100, 100,
                'access_control', 'turnstile', 'ref-1', 'acs.configure', 'acs.configure',
            ],
            'before start time' => [
                Phase6Problem::MARKETPLACE_DELEGATION_NOT_STARTED,
                'active', null, '2027-01-10T05:59:59Z',
                '2027-01-10T06:00:00Z', '2027-01-10T18:00:00Z',
                100, 100,
                'access_control', 'turnstile', 'ref-1', 'acs.configure', 'acs.configure',
            ],
            'at or after end time' => [
                Phase6Problem::MARKETPLACE_DELEGATION_EXPIRED,
                'active', null, '2027-01-10T18:00:00Z',
                '2027-01-10T06:00:00Z', '2027-01-10T18:00:00Z',
                100, 100,
                'access_control', 'turnstile', 'ref-1', 'acs.configure', 'acs.configure',
            ],
            'pending status denied' => [
                Phase6Problem::MARKETPLACE_DELEGATION_NOT_STARTED,
                'pending', null, '2027-01-10T10:00:00Z',
                '2027-01-10T06:00:00Z', '2027-01-10T18:00:00Z',
                100, 100,
                'access_control', 'turnstile', 'ref-1', 'acs.configure', 'acs.configure',
            ],
            'wrong event scope' => [
                Phase6Problem::MARKETPLACE_EVENT_SCOPE_DENIED,
                'active', null, '2027-01-10T10:00:00Z',
                '2027-01-10T06:00:00Z', '2027-01-10T18:00:00Z',
                100, 999,
                'access_control', 'turnstile', 'ref-1', 'acs.configure', 'acs.configure',
            ],
            'missing asset resource' => [
                Phase6Problem::MARKETPLACE_ASSET_SCOPE_DENIED,
                'active', null, '2027-01-10T10:00:00Z',
                '2027-01-10T06:00:00Z', '2027-01-10T18:00:00Z',
                100, 100,
                null, null, null, null, 'acs.configure',
            ],
            'wrong capability' => [
                Phase6Problem::MARKETPLACE_CAPABILITY_DENIED,
                'active', null, '2027-01-10T10:00:00Z',
                '2027-01-10T06:00:00Z', '2027-01-10T18:00:00Z',
                100, 100,
                'access_control', 'turnstile', 'ref-1', 'acs.configure', 'acs.forbidden_capability',
            ],
        ];
    }

    public function test_missing_context_fields_fail_closed(): void
    {
        $decision = $this->guard->decide(new DelegatedControlRequest(
            organizerTenantId: 0,
            actorUserId: 0,
            eventId: 0,
            resourceModule: '',
            resourceType: '',
            resourcePublicReference: '',
            requestedCapability: '',
            now: new DateTimeImmutable,
            existingPermissionAllowed: true,
            delegationPublicId: (string) Str::ulid(),
        ));

        self::assertFalse($decision->allowed);
    }

    public function test_allowed_decision_carries_rental_and_delegation_metadata(): void
    {
        $decision = new DelegatedControlDecision(
            allowed: true,
            rentalPublicId: 'rental-pub',
            delegationPublicId: 'deleg-pub',
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
        );

        self::assertTrue($decision->allowed);
        self::assertSame('rental-pub', $decision->rentalPublicId);
        self::assertSame('deleg-pub', $decision->delegationPublicId);
        self::assertNotNull($decision->startsAt);
        self::assertNotNull($decision->endsAt);
    }

    private function makeRequest(bool $existingPermissionAllowed, ?string $delegationPublicId): DelegatedControlRequest
    {
        return new DelegatedControlRequest(
            organizerTenantId: 1,
            actorUserId: 2,
            eventId: 3,
            resourceModule: 'access_control',
            resourceType: 'turnstile',
            resourcePublicReference: (string) Str::ulid(),
            requestedCapability: 'acs.configure',
            now: new DateTimeImmutable('2027-01-10T10:00:00Z'),
            existingPermissionAllowed: $existingPermissionAllowed,
            delegationPublicId: $delegationPublicId,
        );
    }
}
