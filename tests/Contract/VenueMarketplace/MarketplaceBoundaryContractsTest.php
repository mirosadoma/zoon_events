<?php

namespace Tests\Contract\VenueMarketplace;

use App\Modules\AccessControl\Application\Contracts\DelegatedAcsProvisionRequest;
use App\Modules\Authorization\Application\Contracts\DelegatedControlGuard;
use App\Modules\Authorization\Application\Contracts\DelegatedControlRequest;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterProvisionRequest;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskProvisionRequest;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerProvisionRequest;
use App\Modules\Tenancy\Application\Contracts\OrganizationEligibility;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Domain\Services\MarketplaceCapabilityRegistry;
use App\Modules\VenueMarketplace\Domain\ValueObjects\OpaqueMarketplaceId;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedAcsAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedKioskAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedPrinterAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedScannerAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeOrganizationEligibility;
use DateTimeImmutable;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class MarketplaceBoundaryContractsTest extends TestCase
{
    public function test_opaque_ids_and_camera_catalog_only_behavior_fail_closed(): void
    {
        $id = OpaqueMarketplaceId::generate();
        self::assertTrue(Str::isUlid((string) $id));

        $registry = new MarketplaceCapabilityRegistry;
        self::assertCount(8, $registry->assetTypes());
        self::assertTrue($registry->isCatalogOnly('camera'));
        self::assertSame([], $registry->definition('camera')['capabilities']);

        $this->expectException(\InvalidArgumentException::class);
        $registry->assertCapabilities('camera', ['camera.feed']);
    }

    public function test_operational_ports_are_idempotency_keyed_and_secret_free(): void
    {
        $start = new DateTimeImmutable('2026-07-14T10:00:00Z');
        $end = new DateTimeImmutable('2026-07-14T11:00:00Z');
        $scope = [(string) Str::ulid(), (string) Str::ulid(), (string) Str::ulid(), (string) Str::ulid()];

        $requests = [
            [new FakeDelegatedAcsAssetPort, new DelegatedAcsProvisionRequest($scope[0], $scope[1], $scope[2], $scope[3], 'turnstile', ['acs.configure'], $start, $end, 'corr', 'idem')],
            [new FakeDelegatedKioskAssetPort, new DelegatedKioskProvisionRequest($scope[0], $scope[1], $scope[2], $scope[3], ['kiosk.manage'], $start, $end, 'corr', 'idem')],
            [new FakeDelegatedPrinterAssetPort, new DelegatedPrinterProvisionRequest($scope[0], $scope[1], $scope[2], $scope[3], ['badge.print'], $start, $end, 'corr', 'idem')],
            [new FakeDelegatedScannerAssetPort, new DelegatedScannerProvisionRequest($scope[0], $scope[1], $scope[2], $scope[3], ['checkin.scan.submit'], $start, $end, 'corr', 'idem')],
        ];

        foreach ($requests as [$port, $request]) {
            $result = $port->provision($request);
            self::assertSame('provisioned', $result->status);
            self::assertSame('idem', $request->idempotencyKey);
            $serialized = strtolower(json_encode([$request, $result], JSON_THROW_ON_ERROR));
            self::assertStringNotContainsString('password', $serialized);
            self::assertStringNotContainsString('credential', $serialized);
            self::assertStringNotContainsString('pairing_secret', $serialized);
            self::assertStringNotContainsString('access_token', $serialized);
        }
    }

    public function test_contract_dtos_declare_no_secret_fields(): void
    {
        $classes = [
            DelegatedAcsProvisionRequest::class,
            DelegatedKioskProvisionRequest::class,
            DelegatedPrinterProvisionRequest::class,
            DelegatedScannerProvisionRequest::class,
        ];

        foreach ($classes as $class) {
            $properties = array_map(
                static fn (\ReflectionProperty $property): string => strtolower($property->getName()),
                (new ReflectionClass($class))->getProperties(),
            );
            self::assertEmpty(array_filter($properties, static fn (string $name): bool => preg_match(
                '/secret|credential|password|token|external_reference|binding/',
                $name,
            ) === 1), $class);
        }
    }

    public function test_default_guard_denies_delegated_but_preserves_ordinary_operations(): void
    {
        $guard = app(DelegatedControlGuard::class);
        $base = [
            1, 2, 3, 'access_control', 'turnstile', (string) Str::ulid(),
            'acs.configure', new DateTimeImmutable, true,
        ];

        self::assertTrue($guard->decide(new DelegatedControlRequest(
            $base[0], $base[1], $base[2], $base[3], $base[4], $base[5], $base[6], $base[7], $base[8],
        ))->allowed);
        $decision = $guard->decide(new DelegatedControlRequest(
            $base[0], $base[1], $base[2], $base[3], $base[4], $base[5], $base[6], $base[7], $base[8],
            (string) Str::ulid(),
        ));
        self::assertFalse($decision->allowed);
        self::assertSame('marketplace_delegation_not_found', $decision->reason);
    }

    public function test_audit_contract_rejects_sensitive_payload_shapes(): void
    {
        foreach (['secret_reference', 'raw_credentials', 'request_body', 'binding', 'decision_reason'] as $key) {
            try {
                new MarketplaceAuditEvent(
                    'rental.requested',
                    'organizer',
                    'succeeded',
                    'correlation-id',
                    (string) Str::ulid(),
                    [$key => 'forbidden'],
                );
                self::fail("Audit event accepted {$key}.");
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_foundation_fakes_record_calls_and_fail_deterministically(): void
    {
        $eligibility = new FakeOrganizationEligibility;
        $eligibility->fail = true;
        $result = $eligibility->check(9, OrganizationEligibility::OWN_VENUES);
        self::assertFalse($result->eligible);
        self::assertCount(1, $eligibility->calls);

        $port = new FakeDelegatedKioskAssetPort;
        $port->fail = true;
        $request = new DelegatedKioskProvisionRequest(
            (string) Str::ulid(),
            (string) Str::ulid(),
            (string) Str::ulid(),
            (string) Str::ulid(),
            ['kiosk.manage'],
            new DateTimeImmutable('2026-07-14T10:00:00Z'),
            new DateTimeImmutable('2026-07-14T11:00:00Z'),
            'corr',
            'idem',
        );

        try {
            $port->provision($request);
            self::fail('Fake port did not fail deterministically.');
        } catch (\RuntimeException $exception) {
            self::assertSame('fake_kiosk_failure', $exception->getMessage());
            self::assertCount(1, $port->calls);
        }
    }
}
