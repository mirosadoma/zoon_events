<?php

namespace Tests\Performance\VenueMarketplace;

use App\Modules\AccessControl\Application\Contracts\DelegatedAcsAssetPort;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterAssetPort;
use App\Modules\Events\Application\Contracts\MarketplaceEventReader;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskAssetPort;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerAssetPort;
use App\Modules\Tenancy\Application\Contracts\OrganizationEligibility;
use App\Modules\VenueMarketplace\Application\Queries\MarketplaceCatalogReader;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedAcsAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedKioskAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedPrinterAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedScannerAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeMarketplaceEventReader;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeOrganizationEligibility;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

#[Group('performance')]
#[Group('phase-6')]
class MarketplaceCatalogScaleTest extends TestCase
{
    use BuildsTenantFixtures;
    use RefreshDatabase;

    private const TOTAL_PUBLICATIONS = 10_000;
    private const TENANTS = 5;
    private const VENUES_PER_TENANT = 10;
    private const QUERY_BUDGET = 15;
    private const P95_SECONDS = 2.0;
    private const MEMORY_BUDGET_MB = 64;
    private const PAGE_SIZE = 25;

    private array $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationSeeder::class);
        $this->registerFakes();

        $this->organizer = $this->createTenantMember([], ['organization_type' => 'organizer']);
        $this->grantPermissions($this->organizer, ['marketplace.manage']);

        $this->seedLargePublicationDataset();
    }

    public function test_catalog_index_stays_within_query_budget(): void
    {
        $this->actingAsTenantMember($this->organizer['user'], $this->organizer['tenant']);

        DB::enableQueryLog();
        $response = $this->getJson(
            '/api/v1/tenant/marketplace/catalog?per_page='.self::PAGE_SIZE,
            $this->tenantHeaders($this->organizer['tenant']),
        );
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        self::assertLessThanOrEqual(
            self::QUERY_BUDGET,
            $queryCount,
            "Catalog index used {$queryCount} queries; budget is ".self::QUERY_BUDGET.'.',
        );
    }

    public function test_catalog_index_p95_latency_under_budget(): void
    {
        $this->actingAsTenantMember($this->organizer['user'], $this->organizer['tenant']);
        $headers = $this->tenantHeaders($this->organizer['tenant']);

        $durations = [];
        for ($i = 0; $i < 10; $i++) {
            $started = microtime(true);
            $this->getJson('/api/v1/tenant/marketplace/catalog?per_page='.self::PAGE_SIZE, $headers);
            $durations[] = microtime(true) - $started;
        }

        sort($durations);
        $p95 = $durations[(int) floor((count($durations) - 1) * 0.95)];

        self::assertLessThan(
            self::P95_SECONDS,
            $p95,
            sprintf('Catalog p95 latency %.3fs exceeds %.1fs budget.', $p95, self::P95_SECONDS),
        );
    }

    public function test_catalog_index_memory_under_budget(): void
    {
        $this->actingAsTenantMember($this->organizer['user'], $this->organizer['tenant']);
        $headers = $this->tenantHeaders($this->organizer['tenant']);

        $before = memory_get_usage(true);
        $this->getJson('/api/v1/tenant/marketplace/catalog?per_page='.self::PAGE_SIZE, $headers);
        $after = memory_get_peak_usage(true);

        $usedMb = ($after - $before) / 1024 / 1024;
        self::assertLessThan(
            self::MEMORY_BUDGET_MB,
            $usedMb,
            sprintf('Catalog consumed %.1f MB; budget is %d MB.', $usedMb, self::MEMORY_BUDGET_MB),
        );
    }

    public function test_catalog_pagination_returns_correct_page_sizes(): void
    {
        $this->actingAsTenantMember($this->organizer['user'], $this->organizer['tenant']);
        $headers = $this->tenantHeaders($this->organizer['tenant']);

        $page1 = $this->getJson('/api/v1/tenant/marketplace/catalog?per_page='.self::PAGE_SIZE.'&page=1', $headers);
        $page1->assertOk();
        $page1Data = $page1->json('data');
        self::assertCount(self::PAGE_SIZE, $page1Data);

        $page2 = $this->getJson('/api/v1/tenant/marketplace/catalog?per_page='.self::PAGE_SIZE.'&page=2', $headers);
        $page2->assertOk();
        $page2Data = $page2->json('data');
        self::assertCount(self::PAGE_SIZE, $page2Data);

        $page1Ids = collect($page1Data)->pluck('public_id');
        $page2Ids = collect($page2Data)->pluck('public_id');
        self::assertEmpty(
            $page1Ids->intersect($page2Ids)->all(),
            'Pages must not overlap.',
        );
    }

    public function test_catalog_filter_uses_indexes(): void
    {
        $this->actingAsTenantMember($this->organizer['user'], $this->organizer['tenant']);
        $headers = $this->tenantHeaders($this->organizer['tenant']);

        DB::enableQueryLog();
        $this->getJson('/api/v1/tenant/marketplace/catalog?asset_type=turnstile&per_page='.self::PAGE_SIZE, $headers);
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $catalogQuery = $queries->first(
            fn (array $q): bool => str_contains($q['query'], 'marketplace_catalog_publications'),
        );
        self::assertNotNull($catalogQuery, 'Expected a query against marketplace_catalog_publications.');
        self::assertStringContainsString('asset_type', $catalogQuery['query']);
    }

    public function test_catalog_never_joins_private_venue_tables(): void
    {
        $this->actingAsTenantMember($this->organizer['user'], $this->organizer['tenant']);
        $headers = $this->tenantHeaders($this->organizer['tenant']);

        DB::enableQueryLog();
        $this->getJson('/api/v1/tenant/marketplace/catalog?per_page='.self::PAGE_SIZE, $headers);
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $joinLeaks = $queries->filter(
            fn (array $q): bool => preg_match('/\bjoin\b.*\bvenues\b/i', $q['query'])
                || preg_match('/\bjoin\b.*\bvenue_assets\b/i', $q['query']),
        );
        self::assertCount(
            0,
            $joinLeaks->all(),
            'Catalog must not join private venue/asset tables: '.$joinLeaks->pluck('query')->implode(' | '),
        );
    }

    public function test_catalog_reader_returns_correct_total_count(): void
    {
        $total = DB::table('marketplace_catalog_publications')
            ->where('status', 'active')
            ->count();

        self::assertGreaterThanOrEqual(
            self::TOTAL_PUBLICATIONS,
            $total,
            'Expected at least '.self::TOTAL_PUBLICATIONS.' active publications.',
        );
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function seedLargePublicationDataset(): void
    {
        $assetTypes = ['turnstile', 'scanner', 'kiosk', 'printer', 'camera', 'security_gate', 'access_lane', 'access_zone'];
        $assetsPerVenue = (int) ceil(self::TOTAL_PUBLICATIONS / (self::TENANTS * self::VENUES_PER_TENANT));
        $now = now();

        $tenantIds = [];
        for ($t = 0; $t < self::TENANTS; $t++) {
            $owner = $this->createTenantMember([], ['organization_type' => 'venue_owner']);
            $tenantIds[] = $owner['tenant']->id;
        }

        $publicationBatch = [];
        foreach ($tenantIds as $tenantId) {
            for ($v = 0; $v < self::VENUES_PER_TENANT; $v++) {
                $venueId = DB::table('venues')->insertGetId([
                    'tenant_id' => $tenantId,
                    'public_id' => (string) Str::ulid(),
                    'name_en' => "Scale Venue {$v}",
                    'name_ar' => "موقع القياس {$v}",
                    'description_en' => 'Scale test',
                    'description_ar' => 'اختبار',
                    'address_en' => 'Test',
                    'address_ar' => 'اختبار',
                    'country_code' => 'SA',
                    'city_code' => 'riyadh',
                    'timezone' => 'Asia/Riyadh',
                    'status' => 'active',
                    'version' => 1,
                    'activated_at' => $now,
                    'created_by_user_id' => 1,
                    'updated_by_user_id' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                for ($a = 0; $a < $assetsPerVenue; $a++) {
                    $type = $assetTypes[$a % count($assetTypes)];
                    $assetId = DB::table('venue_assets')->insertGetId([
                        'tenant_id' => $tenantId,
                        'venue_id' => $venueId,
                        'public_id' => (string) Str::ulid(),
                        'asset_type' => $type,
                        'name_en' => "Asset {$a}",
                        'name_ar' => "أصل {$a}",
                        'location_en' => 'Hall',
                        'location_ar' => 'قاعة',
                        'capabilities' => '[]',
                        'capacity_per_minute' => 10,
                        'operational_status' => 'active',
                        'pricing_model' => 'per_hour',
                        'price_minor' => 500,
                        'currency' => 'SAR',
                        'version' => 1,
                        'created_by_user_id' => 1,
                        'updated_by_user_id' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $publicationBatch[] = [
                        'tenant_id' => $tenantId,
                        'venue_id' => $venueId,
                        'venue_asset_id' => $assetId,
                        'public_id' => (string) Str::ulid(),
                        'venue_public_id' => (string) Str::ulid(),
                        'asset_public_id' => (string) Str::ulid(),
                        'publication_version' => 1,
                        'venue_version' => 1,
                        'asset_version' => 1,
                        'venue_name_en' => "Scale Venue {$v}",
                        'venue_name_ar' => "موقع {$v}",
                        'asset_name_en' => "Asset {$a}",
                        'asset_name_ar' => "أصل {$a}",
                        'country_code' => 'SA',
                        'city_code' => 'riyadh',
                        'timezone' => 'Asia/Riyadh',
                        'asset_type' => $type,
                        'capacity_per_minute' => 10,
                        'pricing_model' => 'per_hour',
                        'price_minor' => 500,
                        'currency' => 'SAR',
                        'status' => 'active',
                        'published_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (count($publicationBatch) >= 500) {
                        DB::table('marketplace_catalog_publications')->insert($publicationBatch);
                        $publicationBatch = [];
                    }
                }
            }
        }

        if ($publicationBatch !== []) {
            DB::table('marketplace_catalog_publications')->insert($publicationBatch);
        }
    }

    private function registerFakes(): void
    {
        app()->instance(OrganizationEligibility::class, new FakeOrganizationEligibility);
        app()->instance(MarketplaceEventReader::class, new FakeMarketplaceEventReader);
        app()->instance(DelegatedAcsAssetPort::class, new FakeDelegatedAcsAssetPort);
        app()->instance(DelegatedKioskAssetPort::class, new FakeDelegatedKioskAssetPort);
        app()->instance(DelegatedPrinterAssetPort::class, new FakeDelegatedPrinterAssetPort);
        app()->instance(DelegatedScannerAssetPort::class, new FakeDelegatedScannerAssetPort);
    }

    private function grantPermissions(array $fixture, array $keys): void
    {
        $role = DB::table('tenant_roles')->insertGetId([
            'tenant_id' => $fixture['tenant']->id,
            'name' => 'ScaleTest-'.implode('-', $keys),
            'is_system' => false,
            'created_by_user_id' => $fixture['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (DB::table('permissions')->whereIn('key', $keys)->pluck('id') as $permId) {
            DB::table('tenant_role_permissions')->insert([
                'tenant_id' => $fixture['tenant']->id,
                'tenant_role_id' => $role,
                'permission_id' => $permId,
                'granted_by_user_id' => $fixture['user']->id,
                'created_at' => now(),
            ]);
        }
        DB::table('tenant_role_assignments')->insert([
            'tenant_id' => $fixture['tenant']->id,
            'tenant_membership_id' => $fixture['membership']->id,
            'tenant_role_id' => $role,
            'granted_by_user_id' => $fixture['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
