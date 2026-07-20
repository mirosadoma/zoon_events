<?php

namespace Tests\Feature\Kiosk;

use App\Modules\Kiosk\Application\Contracts\DelegatedKioskAssetPort;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskProvisionRequest;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskReleaseRequest;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedKioskAssetPort;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MarketplaceDelegatedKioskIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private FakeDelegatedKioskAssetPort $port;

    protected function setUp(): void
    {
        parent::setUp();
        $this->port = new FakeDelegatedKioskAssetPort;
        $this->app->instance(DelegatedKioskAssetPort::class, $this->port);
    }

    public function test_resolved_kiosk_port_provisions_with_manage_capability(): void
    {
        $request = new DelegatedKioskProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            capabilities: ['kiosk.manage'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'kiosk-corr',
            idempotencyKey: 'kiosk-idem',
        );

        $result = $this->port->provision($request);

        self::assertSame('provisioned', $result->status);
        self::assertSame('kiosk', $result->resourceType);
        self::assertSame(['kiosk.manage'], $result->acceptedCapabilities);
    }

    public function test_kiosk_release_after_provision(): void
    {
        $provision = $this->port->provision(new DelegatedKioskProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            capabilities: ['kiosk.manage'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'kiosk-prov-corr',
            idempotencyKey: 'kiosk-prov-idem',
        ));

        $release = $this->port->release(new DelegatedKioskReleaseRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            resourcePublicReference: $provision->resourcePublicReference,
            correlationId: 'kiosk-rel-corr',
            idempotencyKey: 'kiosk-rel-idem',
        ));

        self::assertSame('released', $release->status);
        self::assertCount(2, $this->port->calls);
    }

    public function test_kiosk_failure_records_call_before_throwing(): void
    {
        $this->port->fail = true;

        try {
            $this->port->provision(new DelegatedKioskProvisionRequest(
                organizerTenantId: (string) Str::ulid(),
                eventPublicId: (string) Str::ulid(),
                delegationPublicId: (string) Str::ulid(),
                assetPublicId: (string) Str::ulid(),
                capabilities: ['kiosk.manage'],
                startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
                endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
                correlationId: 'kiosk-fail-corr',
                idempotencyKey: 'kiosk-fail-idem',
            ));
            self::fail('Expected RuntimeException.');
        } catch (\RuntimeException $e) {
            self::assertSame('fake_kiosk_failure', $e->getMessage());
            self::assertCount(1, $this->port->calls);
        }
    }

    public function test_kiosk_provision_and_release_lifecycle(): void
    {
        $delegationPublicId = (string) Str::ulid();

        $provision = $this->port->provision(new DelegatedKioskProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: $delegationPublicId,
            assetPublicId: (string) Str::ulid(),
            capabilities: ['kiosk.manage'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'lifecycle-corr',
            idempotencyKey: 'lifecycle-idem',
        ));

        self::assertSame('provisioned', $provision->status);
        self::assertNotEmpty($provision->resourcePublicReference);

        $release = $this->port->release(new DelegatedKioskReleaseRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: $delegationPublicId,
            assetPublicId: (string) Str::ulid(),
            resourcePublicReference: $provision->resourcePublicReference,
            correlationId: 'lifecycle-rel-corr',
            idempotencyKey: 'lifecycle-rel-idem',
        ));

        self::assertSame('released', $release->status);
    }
}
