<?php

namespace Tests\Feature\BadgePrinting;

use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterAssetPort;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterProvisionRequest;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterReleaseRequest;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedPrinterAssetPort;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MarketplaceDelegatedPrinterIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private FakeDelegatedPrinterAssetPort $port;

    protected function setUp(): void
    {
        parent::setUp();
        $this->port = new FakeDelegatedPrinterAssetPort;
        $this->app->instance(DelegatedPrinterAssetPort::class, $this->port);
    }

    public function test_resolved_printer_port_provisions_with_print_capability(): void
    {
        $request = new DelegatedPrinterProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            capabilities: ['badge.print'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'printer-corr',
            idempotencyKey: 'printer-idem',
        );

        $result = $this->port->provision($request);

        self::assertSame('provisioned', $result->status);
        self::assertSame('printer', $result->resourceType);
        self::assertSame(['badge.print'], $result->acceptedCapabilities);
    }

    public function test_printer_release_after_provision(): void
    {
        $provision = $this->port->provision(new DelegatedPrinterProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            capabilities: ['badge.print'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'printer-prov-corr',
            idempotencyKey: 'printer-prov-idem',
        ));

        $release = $this->port->release(new DelegatedPrinterReleaseRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            resourcePublicReference: $provision->resourcePublicReference,
            correlationId: 'printer-rel-corr',
            idempotencyKey: 'printer-rel-idem',
        ));

        self::assertSame('released', $release->status);
        self::assertCount(2, $this->port->calls);
    }

    public function test_printer_failure_records_call_before_throwing(): void
    {
        $this->port->fail = true;

        try {
            $this->port->provision(new DelegatedPrinterProvisionRequest(
                organizerTenantId: (string) Str::ulid(),
                eventPublicId: (string) Str::ulid(),
                delegationPublicId: (string) Str::ulid(),
                assetPublicId: (string) Str::ulid(),
                capabilities: ['badge.print'],
                startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
                endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
                correlationId: 'printer-fail-corr',
                idempotencyKey: 'printer-fail-idem',
            ));
            self::fail('Expected RuntimeException.');
        } catch (\RuntimeException $e) {
            self::assertSame('fake_printer_failure', $e->getMessage());
            self::assertCount(1, $this->port->calls);
        }
    }

    public function test_printer_provision_and_release_lifecycle(): void
    {
        $delegationPublicId = (string) Str::ulid();

        $provision = $this->port->provision(new DelegatedPrinterProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: $delegationPublicId,
            assetPublicId: (string) Str::ulid(),
            capabilities: ['badge.print'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'lifecycle-corr',
            idempotencyKey: 'lifecycle-idem',
        ));

        self::assertSame('provisioned', $provision->status);

        $release = $this->port->release(new DelegatedPrinterReleaseRequest(
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
