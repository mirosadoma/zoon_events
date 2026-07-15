<?php

namespace Tests\Contract\BadgePrinting;

use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterPortResult;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterProvisionRequest;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterReleaseRequest;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedPrinterAssetPort;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DelegatedPrinterAssetPortTest extends TestCase
{
    private FakeDelegatedPrinterAssetPort $port;

    protected function setUp(): void
    {
        parent::setUp();
        $this->port = new FakeDelegatedPrinterAssetPort;
    }

    public function test_provision_allocates_selected_printer_with_capability(): void
    {
        $request = $this->makeProvisionRequest();

        $result = $this->port->provision($request);

        self::assertSame('provisioned', $result->status);
        self::assertSame('printer', $result->resourceType);
        self::assertNotEmpty($result->resourcePublicReference);
        self::assertSame(['badge.print'], $result->acceptedCapabilities);
    }

    public function test_provision_is_idempotent(): void
    {
        $request = $this->makeProvisionRequest(idempotencyKey: 'idem-printer-1');

        $first = $this->port->provision($request);
        $second = $this->port->provision($request);

        self::assertSame($first->status, $second->status);
        self::assertCount(2, $this->port->calls);
    }

    public function test_release_returns_released_status(): void
    {
        $provision = $this->port->provision($this->makeProvisionRequest());

        $release = $this->port->release(new DelegatedPrinterReleaseRequest(
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
        $releaseRequest = new DelegatedPrinterReleaseRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            resourcePublicReference: 'printer-ref',
            correlationId: 'corr',
            idempotencyKey: 'idem-release-printer',
        );

        $first = $this->port->release($releaseRequest);
        $second = $this->port->release($releaseRequest);

        self::assertSame('released', $first->status);
        self::assertSame('released', $second->status);
    }

    public function test_provision_request_enforces_event_window_binding(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DelegatedPrinterProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            capabilities: ['badge.print'],
            startsAt: new DateTimeImmutable('2027-01-10T10:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T08:00:00Z'),
            correlationId: 'corr',
            idempotencyKey: 'idem',
        );
    }

    public function test_print_permission_composition_rejects_invalid_capabilities(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DelegatedPrinterProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            capabilities: ['badge.print', 'badge.admin'],
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
        $this->expectExceptionMessage('fake_printer_failure');

        $this->port->provision($this->makeProvisionRequest());
    }

    public function test_credential_exclusion_in_serialized_data(): void
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

        new DelegatedPrinterPortResult('unknown', 'printer', 'ref');
    }

    private function makeProvisionRequest(string $idempotencyKey = 'idem-printer'): DelegatedPrinterProvisionRequest
    {
        return new DelegatedPrinterProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            capabilities: ['badge.print'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'corr-printer',
            idempotencyKey: $idempotencyKey,
        );
    }
}
