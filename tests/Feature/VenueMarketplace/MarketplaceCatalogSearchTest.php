<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\ChangeVenueStatusAction;
use App\Modules\VenueMarketplace\Application\Queries\MarketplaceCatalogReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class MarketplaceCatalogSearchTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_filters_stable_public_projection_without_private_source_queries(): void
    {
        $owner = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $inventory = $this->createPublishedMarketplaceInventory($owner, 'catalog-search');
        app(TenantContextStore::class)->clear();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $page = app(MarketplaceCatalogReader::class)->search([
            'city_code' => 'riyadh',
            'asset_type' => 'kiosk',
            'capability' => 'kiosk.manage',
            'minimum_capacity_per_minute' => 10,
            'maximum_price_minor' => 1_000,
            'currency' => 'SAR',
        ]);
        $queries = strtolower(json_encode(DB::getQueryLog(), JSON_THROW_ON_ERROR));
        $serialized = json_encode($page->items[0]->toArray(), JSON_THROW_ON_ERROR);

        self::assertCount(1, $page->items);
        self::assertSame($inventory['venue']->public_id, $page->items[0]->venue_public_id);
        self::assertStringNotContainsString(' from `venues`', $queries);
        self::assertStringNotContainsString(' from `venue_assets`', $queries);
        self::assertStringNotContainsString('venue_asset_bindings', $queries);
        self::assertStringNotContainsString('private-fixture@example.test', $serialized);
        self::assertStringNotContainsString('tenant_id', $serialized);

        $firstPage = app(MarketplaceCatalogReader::class)->search([], pageSize: 2);
        $secondPage = app(MarketplaceCatalogReader::class)->search([], $firstPage->nextCursor, 2);
        self::assertTrue($firstPage->hasMore);
        self::assertNotEmpty($firstPage->nextCursor);
        self::assertSame([], array_intersect(
            array_map(fn ($item) => $item->public_id, $firstPage->items),
            array_map(fn ($item) => $item->public_id, $secondPage->items),
        ));

        app(ChangeVenueStatusAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $inventory['venue']->public_id,
            'suspended',
            'catalog-suspension',
        );
        self::assertCount(0, app(MarketplaceCatalogReader::class)->search([])->items);
    }
}
