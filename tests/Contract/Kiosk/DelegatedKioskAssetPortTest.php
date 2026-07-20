<?php

namespace Tests\Contract\Kiosk;

use App\Modules\Kiosk\Application\Contracts\DelegatedKioskPortResult;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskProvisionRequest;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskReleaseRequest;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedKioskAssetPort;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DelegatedKioskAssetPortTest extends TestCase
{
    private FakeDelegatedKioskAssetPort $port;

    protected function setUp(): void
    {
        parent::setUp();
        $this->port = new FakeDelegatedKioskAssetPort;
    }

    public function test_provision_returns_provisioned_status_for_selected_kiosk(): void
    {
        $request = $this->makeProvisionRequest();

        $result = $this->port->provision($request);

        self::assertSame('provisioned', $result->status);
        self::assertSame('kiosk', $result->resourceType);
        self::assertNotEmpty($result->resourcePublicReference);
        self::assertSame(['kiosk.manage'], $result->acceptedCapabilities);
    }

    public function test_provision_is_idempotent(): void
    {
        $request = $this->makeProvisionRequest(idempotencyKey: 'idem-kiosk-1');

        $first = $this->port->provision($request);
        $second = $this->port->provision($request);

        self::assertSame($first->status, $second->status);
        self::assertCount(2, $this->port->calls);
    }

    public function test_release_returns_released_status(): void
    {
        $provision = $this->port->provision($this->makeProvisionRequest());

        $release = $this->port->release(new DelegatedKioskReleaseRequest(
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
        $releaseRequest = new DelegatedKioskReleaseRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            resourcePublicReference: 'kiosk-ref',
            correlationId: 'corr',
            idempotencyKey: 'idem-release-kiosk',
        );

        $first = $this->port->release($releaseRequest);
        $second = $this->port->release($releaseRequest);

        self::assertSame('released', $first->status);
        self::assertSame('released', $second->status);
    }

    public function test_provision_request_enforces_event_window_binding(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DelegatedKioskProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            capabilities: ['kiosk.manage'],
            startsAt: new DateTimeImmutable('2027-01-10T10:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T08:00:00Z'),
            correlationId: 'corr',
            idempotencyKey: 'idem',
        );
    }

    public function test_provision_request_rejects_invalid_capabilities(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DelegatedKioskProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            capabilities: ['kiosk.manage', 'kiosk.admin'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'corr',
            idempotencyKey: 'idem',
        );
    }

    public function test_deterministic_failure_safe_degraded_result(): void
    {
        $this->port->fail = true;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('fake_kiosk_failure');

        $this->port->provision($this->makeProvisionRequest());
    }

    public function test_pairing_secret_exclusion_in_serialized_data(): void
    {
        $request = $this->makeProvisionRequest();
        $result = $this->port->provision($request);

        $serialized = strtolower(json_encode([$request, $result], JSON_THROW_ON_ERROR));

        self::assertStringNotContainsString('pairing_secret', $serialized);
        self::assertStringNotContainsString('password', $serialized);
        self::assertStringNotContainsString('credential', $serialized);
        self::assertStringNotContainsString('access_token', $serialized);
    }

    public function test_result_rejects_invalid_status(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DelegatedKioskPortResult('broken', 'kiosk', 'ref');
    }

    private function makeProvisionRequest(string $idempotencyKey = 'idem-kiosk'): DelegatedKioskProvisionRequest
    {
        return new DelegatedKioskProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            capabilities: ['kiosk.manage'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'corr-kiosk',
            idempotencyKey: $idempotencyKey,
        );
    }
}
