<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\AccessControl\Application\Contracts\DelegatedAcsAssetPort;
use App\Modules\Authorization\Application\Contracts\DelegatedControlGuard;
use App\Modules\Authorization\Application\Contracts\DelegatedControlRequest;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterAssetPort;
use App\Modules\Events\Application\Contracts\MarketplaceEventReader;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskAssetPort;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerAssetPort;
use App\Modules\Tenancy\Application\Contracts\OrganizationEligibility;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedAcsAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedKioskAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedPrinterAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedScannerAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeMarketplaceEventReader;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeOrganizationEligibility;
use Database\Seeders\PermissionSeeder;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6FoundationSmokeTest extends TestCase
{
    public function test_foundational_contracts_resolve_and_fail_closed_in_one_boot(): void
    {
        app()->instance(OrganizationEligibility::class, new FakeOrganizationEligibility);
        app()->instance(MarketplaceEventReader::class, new FakeMarketplaceEventReader);
        app()->instance(DelegatedAcsAssetPort::class, new FakeDelegatedAcsAssetPort);
        app()->instance(DelegatedKioskAssetPort::class, new FakeDelegatedKioskAssetPort);
        app()->instance(DelegatedPrinterAssetPort::class, new FakeDelegatedPrinterAssetPort);
        app()->instance(DelegatedScannerAssetPort::class, new FakeDelegatedScannerAssetPort);

        self::assertTrue(app(OrganizationEligibility::class)
            ->check(1, OrganizationEligibility::OWN_VENUES)->eligible);
        self::assertSame(
            'marketplace_event_not_found',
            app(MarketplaceEventReader::class)->readOwnedEvent(1, 1)->reason,
        );

        $definitions = collect(PermissionSeeder::definitions())->keyBy('key');
        self::assertSame('tenant', $definitions['venue.manage']['scope']);
        self::assertSame('platform', $definitions['platform.marketplace.view']['scope']);

        $decision = app(DelegatedControlGuard::class)->decide(new DelegatedControlRequest(
            1, 1, 1, 'kiosk', 'kiosk', (string) Str::ulid(), 'kiosk.manage',
            new DateTimeImmutable, true, (string) Str::ulid(),
        ));
        self::assertFalse($decision->allowed);

        new MarketplaceAuditEvent(
            'rental.requested',
            'organizer',
            'succeeded',
            'correlation-id',
            (string) Str::ulid(),
            ['status' => 'requested'],
            organizerTenantId: 1,
        );
        self::assertInstanceOf(DelegatedAcsAssetPort::class, app(DelegatedAcsAssetPort::class));
        self::assertInstanceOf(DelegatedKioskAssetPort::class, app(DelegatedKioskAssetPort::class));
        self::assertInstanceOf(DelegatedPrinterAssetPort::class, app(DelegatedPrinterAssetPort::class));
        self::assertInstanceOf(DelegatedScannerAssetPort::class, app(DelegatedScannerAssetPort::class));
    }
}
