<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\AccessControl\Application\Contracts\DelegatedAcsAssetPort;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterAssetPort;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskAssetPort;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerAssetPort;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\ActivateRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\ApproveRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\ProvisionDelegatedAssetsAction;
use App\Modules\VenueMarketplace\Application\Services\DelegatedAssetProvisionerRegistry;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\ControlDelegation;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedAcsAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedKioskAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedPrinterAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedScannerAssetPort;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class MarketplaceOnPremiseDelegationParityTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_local_operation_with_fake_adapters_succeeds(): void
    {
        $this->bindFakeAdapters();

        [$owner, , $rental] = $this->approvedRental('on-premise');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T06:30:00Z'));

        $result = app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'on-premise-corr',
        );

        self::assertSame('active', $result->status);

        $delegation = ControlDelegation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->first();

        self::assertContains($delegation->status, ['active', 'degraded']);
    }

    public function test_provisioner_registry_resolves_all_module_adapters(): void
    {
        $this->bindFakeAdapters();

        $registry = app(DelegatedAssetProvisionerRegistry::class);

        self::assertInstanceOf(FakeDelegatedAcsAssetPort::class, $registry->resolve('access_control'));
        self::assertInstanceOf(FakeDelegatedKioskAssetPort::class, $registry->resolve('kiosk'));
        self::assertInstanceOf(FakeDelegatedPrinterAssetPort::class, $registry->resolve('badge_printing'));
        self::assertInstanceOf(FakeDelegatedScannerAssetPort::class, $registry->resolve('scanning'));
    }

    public function test_provisioner_registry_rejects_unknown_module(): void
    {
        $registry = app(DelegatedAssetProvisionerRegistry::class);

        $this->expectException(\App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException::class);

        $registry->resolve('nonexistent_module');
    }

    public function test_provision_action_works_with_fake_adapters_without_external_network(): void
    {
        $this->bindFakeAdapters();

        [$owner, , $rental] = $this->approvedRental('no-network');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T06:30:00Z'));

        app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'no-network-corr',
        );

        $delegation = ControlDelegation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->first();

        self::assertContains($delegation->status, ['active', 'degraded']);
        self::assertGreaterThanOrEqual(1, (int) $delegation->provision_attempts);
    }

    public function test_provision_action_idempotent_with_fake_adapters(): void
    {
        $this->bindFakeAdapters();

        [$owner, , $rental] = $this->approvedRental('idempotent-prem');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-10T06:30:00Z'));

        app(ActivateRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->id,
            'idempotent-prem-corr-1',
        );

        $delegation = ControlDelegation::query()->withoutGlobalScopes()
            ->where('rental_request_id', $rental->id)->first();

        $versionAfterFirst = $delegation->version;

        app(ProvisionDelegatedAssetsAction::class)->execute(
            (int) $owner['tenant']->id,
            $delegation->public_id,
            'idempotent-prem-corr-2',
        );

        $delegation->refresh();
        self::assertSame($versionAfterFirst + 1, $delegation->version);
    }

    private function bindFakeAdapters(): void
    {
        $this->app->instance(DelegatedAcsAssetPort::class, new FakeDelegatedAcsAssetPort);
        $this->app->instance(DelegatedKioskAssetPort::class, new FakeDelegatedKioskAssetPort);
        $this->app->instance(DelegatedPrinterAssetPort::class, new FakeDelegatedPrinterAssetPort);
        $this->app->instance(DelegatedScannerAssetPort::class, new FakeDelegatedScannerAssetPort);
    }

    /**
     * @return array{0:array,1:array,2:mixed}
     */
    private function approvedRental(string $key): array
    {
        $this->freezeMarketplaceClock();
        $organizer = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        $event = $this->createMarketplaceEvent($organizer['tenant'], $organizer['user']);
        $owner = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $inventory = $this->createPublishedMarketplaceInventory($owner, $key);
        app(TenantContextStore::class)->clear();

        $publicationPublicId = $inventory['assets'][3]->publications()
            ->where('status', 'active')
            ->value('public_id');

        $rental = $this->createSubmittedMarketplaceRental(
            $organizer,
            $event,
            [$publicationPublicId],
            "{$key}-rental",
        );

        $rental = app(ApproveRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            1,
            "{$key}-approve-idem",
            "{$key}-approve-corr",
        );

        return [$owner, $organizer, $rental];
    }
}
