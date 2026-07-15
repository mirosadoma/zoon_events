<?php

namespace Tests\Feature\AccessControl;

use App\Modules\AccessControl\Application\Contracts\DelegatedAcsAssetPort;
use App\Modules\AccessControl\Application\Contracts\DelegatedAcsProvisionRequest;
use App\Modules\AccessControl\Application\Contracts\DelegatedAcsReleaseRequest;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedAcsAssetPort;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MarketplaceDelegatedAcsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private FakeDelegatedAcsAssetPort $port;

    protected function setUp(): void
    {
        parent::setUp();
        $this->port = new FakeDelegatedAcsAssetPort;
        $this->app->instance(DelegatedAcsAssetPort::class, $this->port);
    }

    public function test_resolved_acs_port_provisions_turnstile(): void
    {
        $request = new DelegatedAcsProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            assetType: 'turnstile',
            capabilities: ['acs.configure'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'acs-corr',
            idempotencyKey: 'acs-idem',
        );

        $result = $this->port->provision($request);

        self::assertSame('provisioned', $result->status);
        self::assertCount(1, $this->port->calls);
        self::assertSame('provision', $this->port->calls[0]['operation']);
    }

    public function test_resolved_acs_port_provisions_security_gate(): void
    {
        $request = new DelegatedAcsProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            assetType: 'security_gate',
            capabilities: ['acs.configure'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'gate-corr',
            idempotencyKey: 'gate-idem',
        );

        $result = $this->port->provision($request);

        self::assertSame('provisioned', $result->status);
    }

    public function test_resolved_acs_port_provisions_access_lane(): void
    {
        $request = new DelegatedAcsProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            assetType: 'access_lane',
            capabilities: ['acs.configure'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'lane-corr',
            idempotencyKey: 'lane-idem',
        );

        $result = $this->port->provision($request);

        self::assertSame('provisioned', $result->status);
    }

    public function test_resolved_acs_port_provisions_access_zone(): void
    {
        $request = new DelegatedAcsProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            assetType: 'access_zone',
            capabilities: ['acs.configure'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'zone-corr',
            idempotencyKey: 'zone-idem',
        );

        $result = $this->port->provision($request);

        self::assertSame('provisioned', $result->status);
    }

    public function test_acs_release_after_provision(): void
    {
        $provisionRequest = new DelegatedAcsProvisionRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            assetType: 'turnstile',
            capabilities: ['acs.configure'],
            startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
            endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
            correlationId: 'release-corr',
            idempotencyKey: 'release-prov-idem',
        );

        $provision = $this->port->provision($provisionRequest);

        $releaseRequest = new DelegatedAcsReleaseRequest(
            organizerTenantId: (string) Str::ulid(),
            eventPublicId: (string) Str::ulid(),
            delegationPublicId: (string) Str::ulid(),
            assetPublicId: (string) Str::ulid(),
            resourcePublicReference: $provision->resourcePublicReference,
            correlationId: 'release-corr',
            idempotencyKey: 'release-idem',
        );

        $release = $this->port->release($releaseRequest);

        self::assertSame('released', $release->status);
        self::assertCount(2, $this->port->calls);
    }

    public function test_acs_failure_records_call_before_throwing(): void
    {
        $this->port->fail = true;

        try {
            $this->port->provision(new DelegatedAcsProvisionRequest(
                organizerTenantId: (string) Str::ulid(),
                eventPublicId: (string) Str::ulid(),
                delegationPublicId: (string) Str::ulid(),
                assetPublicId: (string) Str::ulid(),
                assetType: 'turnstile',
                capabilities: ['acs.configure'],
                startsAt: new DateTimeImmutable('2027-01-10T06:00:00Z'),
                endsAt: new DateTimeImmutable('2027-01-10T18:00:00Z'),
                correlationId: 'fail-corr',
                idempotencyKey: 'fail-idem',
            ));
            self::fail('Expected RuntimeException.');
        } catch (\RuntimeException $e) {
            self::assertSame('fake_acs_failure', $e->getMessage());
            self::assertCount(1, $this->port->calls);
        }
    }
}
