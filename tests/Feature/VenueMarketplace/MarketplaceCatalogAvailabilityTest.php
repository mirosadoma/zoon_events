<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Queries\MarketplaceCatalogReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class MarketplaceCatalogAvailabilityTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_utc_window_must_be_fully_contained_with_adjacent_boundaries_allowed(): void
    {
        $this->freezeMarketplaceClock();
        $owner = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $this->createPublishedMarketplaceInventory($owner, 'catalog-availability');
        app(TenantContextStore::class)->clear();
        $reader = app(MarketplaceCatalogReader::class);

        $contained = $reader->search([
            'asset_type' => 'kiosk',
            'requested_start_at' => '2027-01-10T06:00:00Z',
            'requested_end_at' => '2027-01-10T15:00:00Z',
        ]);
        $outside = $reader->search([
            'asset_type' => 'kiosk',
            'requested_start_at' => '2027-01-10T05:59:59Z',
            'requested_end_at' => '2027-01-10T15:00:00Z',
        ]);

        self::assertCount(1, $contained->items);
        self::assertTrue($contained->items[0]->available_for_requested_window);
        self::assertCount(0, $outside->items);
    }
}
