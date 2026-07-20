<?php

namespace Tests\Feature\Scanning;

use App\Modules\Scanning\Application\Contracts\DelegatedScannerAssetPort;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerProvisionRequest;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerReleaseRequest;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedScannerAssetPort;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MarketplaceDelegatedScannerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private FakeDelegatedScannerAssetPort $port;

    protected function setUp(): void
    {
        parent::setUp();
        $this->port = new FakeDelegatedScannerAssetPort;
        $this->app->instance(DelegatedScannerAssetPort::class, $this->port);
    }

    public function test_resolved_scanner_port_provisions_with_scan_capability(): void
    {
        $request = new DelegatedScannerProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            capabilities: ['checkin.scan.submit'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'scanner-corr',
            idempotencyKey: 'scanner-idem',
        );

        $result = $this->port->provision($request);

        self::assertSame('provisioned', $result->status);
        self::assertSame('scanner', $result->resourceType);
        self::assertSame(['checkin.scan.submit'], $result->acceptedCapabilities);
    }

    public function test_scanner_release_after_provision(): void
    {
        $provision = $this->port->provision(new DelegatedScannerProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            capabilities: ['checkin.scan.submit'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'scanner-prov-corr',
            idempotencyKey: 'scanner-prov-idem',
        ));

        $release = $this->port->release(new DelegatedScannerReleaseRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            resourcePublicReference: $provision->resourcePublicReference,
            correlationId: 'scanner-rel-corr',
            idempotencyKey: 'scanner-rel-idem',
        ));

        self::assertSame('released', $release->status);
        self::assertCount(2, $this->port->calls);
    }

    public function test_scanner_failure_records_call_before_throwing(): void
    {
        $this->port->fail = true;

        try {
            $this->port->provision(new DelegatedScannerProvisionRequest(
                organizerTenantId: (string) Str::ulid(),
                eventPublicId: (string) Str::ulid(),
                delegationPublicId: (string) Str::ulid(),
                assetPublicId: (string) Str::ulid(),
                capabilities: ['checkin.scan.submit'],
                startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
                endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
                correlationId: 'scanner-fail-corr',
                idempotencyKey: 'scanner-fail-idem',
            ));
            self::fail('Expected RuntimeException.');
        } catch (\RuntimeException $e) {
            self::assertSame('fake_scanner_failure', $e->getMessage());
            self::assertCount(1, $this->port->calls);
        }
    }

    public function test_scanner_provision_and_release_lifecycle(): void
    {
        $delegationPublicId = (string) Str::ulid();

        $provision = $this->port->provision(new DelegatedScannerProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: $delegationPublicId,
            assetPublicId: (string) Str::ulid(),
            capabilities: ['checkin.scan.submit'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'lifecycle-corr',
            idempotencyKey: 'lifecycle-idem',
        ));

        self::assertSame('provisioned', $provision->status);

        $release = $this->port->release(new DelegatedScannerReleaseRequest(
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

    public function test_duplicate_provision_requests_tracked(): void
    {
        $request = new DelegatedScannerProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            capabilities: ['checkin.scan.submit'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'dup-corr',
            idempotencyKey: 'dup-idem',
        );

        $this->port->provision($request);
        $this->port->provision($request);

        self::assertCount(2, $this->port->calls);
        self::assertSame('provision', $this->port->calls[0]['operation']);
        self::assertSame('provision', $this->port->calls[1]['operation']);
    }
}
