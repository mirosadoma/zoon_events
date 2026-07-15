<?php

namespace Tests\Contract\AccessControl;

use App\Modules\AccessControl\Application\Contracts\DelegatedAcsPortResult;
use App\Modules\AccessControl\Application\Contracts\DelegatedAcsProvisionRequest;
use App\Modules\AccessControl\Application\Contracts\DelegatedAcsReleaseRequest;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedAcsAssetPort;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DelegatedAcsAssetPortTest extends TestCase
{
    private FakeDelegatedAcsAssetPort $port;

    protected function setUp(): void
    {
        parent::setUp();
        $this->port = new FakeDelegatedAcsAssetPort;
    }

    public function test_provision_returns_provisioned_status_with_opaque_reference(): void
    {
        $request = $this->makeProvisionRequest();

        $result = $this->port->provision($request);

        self::assertSame('provisioned', $result->status);
        self::assertNotEmpty($result->resourcePublicReference);
        self::assertSame($request->capabilities, $result->acceptedCapabilities);
    }

    public function test_provision_is_idempotent_with_same_key(): void
    {
        $request = $this->makeProvisionRequest(idempotencyKey: 'idem-acs-1');

        $first = $this->port->provision($request);
        $second = $this->port->provision($request);

        self::assertSame($first->status, $second->status);
        self::assertCount(2, $this->port->calls);
    }

    public function test_release_returns_released_status(): void
    {
        $provision = $this->port->provision($this->makeProvisionRequest());

        $release = $this->port->release(new DelegatedAcsReleaseRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            resourcePublicReference: $provision->resourcePublicReference,
            correlationId: 'corr-release',
            idempotencyKey: 'idem-release',
        ));

        self::assertSame('released', $release->status);
    }

    public function test_release_is_idempotent(): void
    {
        $releaseRequest = new DelegatedAcsReleaseRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            resourcePublicReference: 'acs-ref',
            correlationId: 'corr',
            idempotencyKey: 'idem-release-2',
        );

        $first = $this->port->release($releaseRequest);
        $second = $this->port->release($releaseRequest);

        self::assertSame('released', $first->status);
        self::assertSame('released', $second->status);
    }

    public function test_provision_request_enforces_event_window_binding(): void
    {
        $start = new DateTimeImmutable('2027-01-10T10:00:00Z');
        $end = new DateTimeImmutable('2027-01-10T08:00:00Z');

        $this->expectException(\InvalidArgumentException::class);

        new DelegatedAcsProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            assetType: 'turnstile',
            capabilities: ['acs.configure'],
            startsAt: $start,
            endsAt: $end,
            correlationId: 'corr',
            idempotencyKey: 'idem',
        );
    }

    public function test_provision_request_rejects_invalid_asset_types(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DelegatedAcsProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            assetType: 'kiosk',
            capabilities: ['acs.configure'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'corr',
            idempotencyKey: 'idem',
        );
    }

    public function test_provision_request_enforces_selected_zones_and_lanes_capabilities(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DelegatedAcsProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            assetType: 'turnstile',
            capabilities: ['acs.configure', 'acs.admin.override'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'corr',
            idempotencyKey: 'idem',
        );
    }

    public function test_deterministic_failure_mode(): void
    {
        $this->port->fail = true;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('fake_acs_failure');

        $this->port->provision($this->makeProvisionRequest());
    }

    public function test_no_credential_exposure_in_serialized_request_and_result(): void
    {
        $request = $this->makeProvisionRequest();
        $result = $this->port->provision($request);

        $serialized = strtolower(json_encode([$request, $result], JSON_THROW_ON_ERROR));

        self::assertStringNotContainsString('password', $serialized);
        self::assertStringNotContainsString('credential', $serialized);
        self::assertStringNotContainsString('access_token', $serialized);
        self::assertStringNotContainsString('pairing_secret', $serialized);
    }

    public function test_result_rejects_invalid_status(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DelegatedAcsPortResult('invalid_status', 'acs', 'ref');
    }

    private function makeProvisionRequest(string $idempotencyKey = 'idem-acs'): DelegatedAcsProvisionRequest
    {
        return new DelegatedAcsProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            assetType: 'turnstile',
            capabilities: ['acs.configure'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'corr-acs',
            idempotencyKey: $idempotencyKey,
        );
    }
}
