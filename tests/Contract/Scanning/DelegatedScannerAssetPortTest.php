<?php

namespace Tests\Contract\Scanning;

use App\Modules\Scanning\Application\Contracts\DelegatedScannerPortResult;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerProvisionRequest;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerReleaseRequest;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedScannerAssetPort;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DelegatedScannerAssetPortTest extends TestCase
{
    private FakeDelegatedScannerAssetPort $port;

    protected function setUp(): void
    {
        parent::setUp();
        $this->port = new FakeDelegatedScannerAssetPort;
    }

    public function test_provision_allocates_selected_scanner_with_capability(): void
    {
        $request = $this->makeProvisionRequest();

        $result = $this->port->provision($request);

        self::assertSame('provisioned', $result->status);
        self::assertSame('scanner', $result->resourceType);
        self::assertNotEmpty($result->resourcePublicReference);
        self::assertSame(['checkin.scan.submit'], $result->acceptedCapabilities);
    }

    public function test_provision_is_idempotent(): void
    {
        $request = $this->makeProvisionRequest(idempotencyKey: 'idem-scanner-1');

        $first = $this->port->provision($request);
        $second = $this->port->provision($request);

        self::assertSame($first->status, $second->status);
        self::assertCount(2, $this->port->calls);
    }

    public function test_release_returns_released_status(): void
    {
        $provision = $this->port->provision($this->makeProvisionRequest());

        $release = $this->port->release(new DelegatedScannerReleaseRequest(
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
        $releaseRequest = new DelegatedScannerReleaseRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            resourcePublicReference: 'scanner-ref',
            correlationId: 'corr',
            idempotencyKey: 'idem-release-scanner',
        );

        $first = $this->port->release($releaseRequest);
        $second = $this->port->release($releaseRequest);

        self::assertSame('released', $first->status);
        self::assertSame('released', $second->status);
    }

    public function test_provision_request_enforces_event_window_binding(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DelegatedScannerProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            capabilities: ['checkin.scan.submit'],
            startsAt: new DateTimeImmutable('2027-01-10T10:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T08:00:00Z'),
            correlationId: 'corr',
            idempotencyKey: 'idem',
        );
    }

    public function test_scan_permission_composition_rejects_invalid_capabilities(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DelegatedScannerProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            capabilities: ['checkin.scan.submit', 'checkin.scan.override'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'corr',
            idempotencyKey: 'idem',
        );
    }

    public function test_duplicate_replay_safety(): void
    {
        $request = $this->makeProvisionRequest(idempotencyKey: 'replay-key');

        $this->port->provision($request);
        $this->port->provision($request);
        $this->port->provision($request);

        self::assertCount(3, $this->port->calls);
        foreach ($this->port->calls as $call) {
            self::assertSame('provision', $call['operation']);
            self::assertSame('replay-key', $call['request']->idempotencyKey);
        }
    }

    public function test_deterministic_failure_mode(): void
    {
        $this->port->fail = true;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('fake_scanner_failure');

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

        new DelegatedScannerPortResult('invalid', 'scanner', 'ref');
    }

    private function makeProvisionRequest(string $idempotencyKey = 'idem-scanner'): DelegatedScannerProvisionRequest
    {
        return new DelegatedScannerProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            capabilities: ['checkin.scan.submit'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'corr-scanner',
            idempotencyKey: $idempotencyKey,
        );
    }
}
