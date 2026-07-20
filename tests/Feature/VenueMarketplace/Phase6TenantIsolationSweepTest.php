<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\AccessControl\Application\Contracts\DelegatedAcsAssetPort;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterAssetPort;
use App\Modules\Events\Application\Contracts\MarketplaceEventReader;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskAssetPort;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerAssetPort;
use App\Modules\Tenancy\Application\Contracts\OrganizationEligibility;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedAcsAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedKioskAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedPrinterAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeDelegatedScannerAssetPort;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeMarketplaceEventReader;
use App\Modules\VenueMarketplace\Testing\Fakes\FakeOrganizationEligibility;
use Database\Factories\VenueMarketplaceFactory;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

class Phase6TenantIsolationSweepTest extends TestCase
{
    use BuildsTenantFixtures;
    use RefreshDatabase;

    private array $owner;
    private array $organizer;
    private array $foreignOwner;
    private array $foreignOrganizer;
    private array $inventory;
    private string $rentalPublicId;
    private string $delegationPublicId;
    private string $statementPublicId;
    private string $disputePublicId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationSeeder::class);

        app()->instance(OrganizationEligibility::class, new FakeOrganizationEligibility);
        app()->instance(MarketplaceEventReader::class, new FakeMarketplaceEventReader);
        app()->instance(DelegatedAcsAssetPort::class, new FakeDelegatedAcsAssetPort);
        app()->instance(DelegatedKioskAssetPort::class, new FakeDelegatedKioskAssetPort);
        app()->instance(DelegatedPrinterAssetPort::class, new FakeDelegatedPrinterAssetPort);
        app()->instance(DelegatedScannerAssetPort::class, new FakeDelegatedScannerAssetPort);

        $this->owner = $this->createTenantMember([], ['organization_type' => 'venue_owner']);
        $this->organizer = $this->createTenantMember([], ['organization_type' => 'organizer']);
        $this->foreignOwner = $this->createTenantMember([], ['organization_type' => 'venue_owner']);
        $this->foreignOrganizer = $this->createTenantMember([], ['organization_type' => 'organizer']);

        $this->grantTenantPermissions($this->owner, ['venue.manage', 'marketplace.manage', 'rentals.approve', 'reports.view']);
        $this->grantTenantPermissions($this->organizer, ['marketplace.manage', 'reports.view']);
        $this->grantTenantPermissions($this->foreignOwner, ['venue.manage', 'marketplace.manage', 'rentals.approve', 'reports.view']);
        $this->grantTenantPermissions($this->foreignOrganizer, ['marketplace.manage', 'reports.view']);

        $factory = app(VenueMarketplaceFactory::class);
        $this->inventory = $factory->createPublishedInventory(
            $this->owner['tenant']->id,
            $this->owner['user']->id,
            'iso',
        );

        $event = $factory->createOrganizerEvent(
            $this->organizer['tenant']->id,
            $this->organizer['user']->id,
            'iso-event',
        );

        $publicationIds = DB::table('marketplace_catalog_publications')
            ->where('tenant_id', $this->owner['tenant']->id)
            ->where('status', 'active')
            ->limit(2)
            ->pluck('public_id')
            ->all();

        $rental = $factory->createSubmittedRental(
            $this->organizer['tenant']->id,
            $this->organizer['user']->id,
            $event->id,
            $publicationIds,
            key: 'iso-rental',
        );
        $this->rentalPublicId = $rental->public_id;

        $this->actingAsTenantMember($this->owner['user'], $this->owner['tenant']);
        $this->postJson(
            "/api/v1/tenant/marketplace/rentals/{$this->rentalPublicId}/approve",
            [],
            $this->tenantHeaders($this->owner['tenant']),
        );

        $this->delegationPublicId = DB::table('control_delegations')
            ->where('rental_request_id', $rental->id)
            ->value('public_id') ?? 'none';

        $this->statementPublicId = DB::table('settlement_statements')
            ->where('rental_request_id', $rental->id)
            ->value('public_id') ?? 'none';

        $this->disputePublicId = DB::table('marketplace_disputes')
            ->where('rental_request_id', $rental->id)
            ->value('public_id') ?? 'none';
    }

    // ─── Venue owner isolation ───────────────────────────────────

    public function test_foreign_owner_cannot_list_venues(): void
    {
        $this->actingAsTenantMember($this->foreignOwner['user'], $this->foreignOwner['tenant']);
        $response = $this->getJson('/api/v1/tenant/venues', $this->tenantHeaders($this->foreignOwner['tenant']));
        $response->assertOk();
        self::assertEmpty($response->json('data'));
    }

    public function test_foreign_owner_cannot_read_venue_by_public_id(): void
    {
        $this->actingAsTenantMember($this->foreignOwner['user'], $this->foreignOwner['tenant']);
        $venuePublicId = $this->inventory['venue']->public_id;
        $response = $this->getJson(
            "/api/v1/tenant/venues/{$venuePublicId}",
            $this->tenantHeaders($this->foreignOwner['tenant']),
        );
        $response->assertStatus(404);
    }

    public function test_foreign_owner_cannot_update_venue(): void
    {
        $this->actingAsTenantMember($this->foreignOwner['user'], $this->foreignOwner['tenant']);
        $venuePublicId = $this->inventory['venue']->public_id;
        $response = $this->patchJson(
            "/api/v1/tenant/venues/{$venuePublicId}",
            ['name_en' => 'Hijacked'],
            $this->tenantHeaders($this->foreignOwner['tenant']),
        );
        $response->assertStatus(404);
    }

    public function test_foreign_owner_cannot_archive_venue(): void
    {
        $this->actingAsTenantMember($this->foreignOwner['user'], $this->foreignOwner['tenant']);
        $venuePublicId = $this->inventory['venue']->public_id;
        $response = $this->postJson(
            "/api/v1/tenant/venues/{$venuePublicId}/archive",
            [],
            $this->tenantHeaders($this->foreignOwner['tenant']),
        );
        $response->assertStatus(404);
    }

    public function test_foreign_owner_cannot_change_venue_status(): void
    {
        $this->actingAsTenantMember($this->foreignOwner['user'], $this->foreignOwner['tenant']);
        $venuePublicId = $this->inventory['venue']->public_id;
        $response = $this->postJson(
            "/api/v1/tenant/venues/{$venuePublicId}/status",
            ['status' => 'suspended'],
            $this->tenantHeaders($this->foreignOwner['tenant']),
        );
        $response->assertStatus(404);
    }

    // ─── Asset isolation ─────────────────────────────────────────

    public function test_foreign_owner_cannot_list_assets(): void
    {
        $this->actingAsTenantMember($this->foreignOwner['user'], $this->foreignOwner['tenant']);
        $venuePublicId = $this->inventory['venue']->public_id;
        $response = $this->getJson(
            "/api/v1/tenant/venues/{$venuePublicId}/assets",
            $this->tenantHeaders($this->foreignOwner['tenant']),
        );
        $response->assertStatus(404);
    }

    public function test_foreign_owner_cannot_read_asset(): void
    {
        $this->actingAsTenantMember($this->foreignOwner['user'], $this->foreignOwner['tenant']);
        $venuePublicId = $this->inventory['venue']->public_id;
        $assetPublicId = $this->inventory['assets'][0]->public_id;
        $response = $this->getJson(
            "/api/v1/tenant/venues/{$venuePublicId}/assets/{$assetPublicId}",
            $this->tenantHeaders($this->foreignOwner['tenant']),
        );
        $response->assertStatus(404);
    }

    public function test_foreign_owner_cannot_publish_asset(): void
    {
        $this->actingAsTenantMember($this->foreignOwner['user'], $this->foreignOwner['tenant']);
        $venuePublicId = $this->inventory['venue']->public_id;
        $assetPublicId = $this->inventory['assets'][0]->public_id;
        $response = $this->postJson(
            "/api/v1/tenant/venues/{$venuePublicId}/assets/{$assetPublicId}/publication",
            [],
            $this->tenantHeaders($this->foreignOwner['tenant']),
        );
        $response->assertStatus(404);
    }

    // ─── Rental isolation ────────────────────────────────────────

    public function test_foreign_organizer_cannot_list_rentals(): void
    {
        $this->actingAsTenantMember($this->foreignOrganizer['user'], $this->foreignOrganizer['tenant']);
        $response = $this->getJson(
            '/api/v1/tenant/marketplace/rentals',
            $this->tenantHeaders($this->foreignOrganizer['tenant']),
        );
        $response->assertOk();
        self::assertEmpty($response->json('data'));
    }

    public function test_foreign_organizer_cannot_read_rental(): void
    {
        $this->actingAsTenantMember($this->foreignOrganizer['user'], $this->foreignOrganizer['tenant']);
        $response = $this->getJson(
            "/api/v1/tenant/marketplace/rentals/{$this->rentalPublicId}",
            $this->tenantHeaders($this->foreignOrganizer['tenant']),
        );
        $response->assertStatus(404);
    }

    public function test_foreign_organizer_cannot_cancel_rental(): void
    {
        $this->actingAsTenantMember($this->foreignOrganizer['user'], $this->foreignOrganizer['tenant']);
        $response = $this->postJson(
            "/api/v1/tenant/marketplace/rentals/{$this->rentalPublicId}/cancel",
            [],
            $this->tenantHeaders($this->foreignOrganizer['tenant']),
        );
        $response->assertStatus(404);
    }

    public function test_foreign_owner_cannot_approve_rental(): void
    {
        $this->actingAsTenantMember($this->foreignOwner['user'], $this->foreignOwner['tenant']);
        $response = $this->postJson(
            "/api/v1/tenant/marketplace/rentals/{$this->rentalPublicId}/approve",
            [],
            $this->tenantHeaders($this->foreignOwner['tenant']),
        );
        $response->assertStatus(404);
    }

    public function test_foreign_owner_cannot_reject_rental(): void
    {
        $this->actingAsTenantMember($this->foreignOwner['user'], $this->foreignOwner['tenant']);
        $response = $this->postJson(
            "/api/v1/tenant/marketplace/rentals/{$this->rentalPublicId}/reject",
            ['reason' => 'no'],
            $this->tenantHeaders($this->foreignOwner['tenant']),
        );
        $response->assertStatus(404);
    }

    public function test_foreign_owner_cannot_revoke_rental(): void
    {
        $this->actingAsTenantMember($this->foreignOwner['user'], $this->foreignOwner['tenant']);
        $response = $this->postJson(
            "/api/v1/tenant/marketplace/rentals/{$this->rentalPublicId}/revoke",
            ['reason' => 'hijack'],
            $this->tenantHeaders($this->foreignOwner['tenant']),
        );
        $response->assertStatus(404);
    }

    // ─── Delegation isolation ────────────────────────────────────

    public function test_foreign_organizer_cannot_read_delegation(): void
    {
        $this->actingAsTenantMember($this->foreignOrganizer['user'], $this->foreignOrganizer['tenant']);
        $response = $this->getJson(
            "/api/v1/tenant/marketplace/rentals/{$this->rentalPublicId}/delegation",
            $this->tenantHeaders($this->foreignOrganizer['tenant']),
        );
        $response->assertStatus(404);
    }

    // ─── Statement isolation ─────────────────────────────────────

    public function test_foreign_tenant_cannot_list_statements(): void
    {
        $this->actingAsTenantMember($this->foreignOwner['user'], $this->foreignOwner['tenant']);
        $response = $this->getJson(
            '/api/v1/tenant/marketplace/statements',
            $this->tenantHeaders($this->foreignOwner['tenant']),
        );
        $response->assertOk();
        self::assertEmpty($response->json('data'));
    }

    public function test_foreign_tenant_cannot_read_statement(): void
    {
        if ($this->statementPublicId === 'none') {
            self::markTestSkipped('No statement was generated in setUp.');
        }
        $this->actingAsTenantMember($this->foreignOwner['user'], $this->foreignOwner['tenant']);
        $response = $this->getJson(
            "/api/v1/tenant/marketplace/statements/{$this->statementPublicId}",
            $this->tenantHeaders($this->foreignOwner['tenant']),
        );
        $response->assertStatus(404);
    }

    public function test_foreign_tenant_cannot_export_statement(): void
    {
        if ($this->statementPublicId === 'none') {
            self::markTestSkipped('No statement was generated in setUp.');
        }
        $this->actingAsTenantMember($this->foreignOwner['user'], $this->foreignOwner['tenant']);
        $response = $this->getJson(
            "/api/v1/tenant/marketplace/statements/{$this->statementPublicId}/export",
            $this->tenantHeaders($this->foreignOwner['tenant']),
        );
        $response->assertStatus(404);
    }

    // ─── Dispute isolation ───────────────────────────────────────

    public function test_foreign_tenant_cannot_open_dispute_on_foreign_statement(): void
    {
        if ($this->statementPublicId === 'none') {
            self::markTestSkipped('No statement was generated in setUp.');
        }
        $this->actingAsTenantMember($this->foreignOrganizer['user'], $this->foreignOrganizer['tenant']);
        $response = $this->postJson(
            "/api/v1/tenant/marketplace/statements/{$this->statementPublicId}/disputes",
            ['reason' => 'test', 'description' => 'Test dispute'],
            $this->tenantHeaders($this->foreignOrganizer['tenant']),
        );
        $response->assertStatus(404);
    }

    public function test_foreign_tenant_cannot_read_dispute(): void
    {
        if ($this->disputePublicId === 'none') {
            self::markTestSkipped('No dispute was generated in setUp.');
        }
        $this->actingAsTenantMember($this->foreignOrganizer['user'], $this->foreignOrganizer['tenant']);
        $response = $this->getJson(
            "/api/v1/tenant/marketplace/disputes/{$this->disputePublicId}",
            $this->tenantHeaders($this->foreignOrganizer['tenant']),
        );
        $response->assertStatus(404);
    }

    // ─── Opaque ID probing ───────────────────────────────────────

    public function test_fabricated_opaque_ids_return_404_not_error(): void
    {
        $this->actingAsTenantMember($this->foreignOwner['user'], $this->foreignOwner['tenant']);
        $fakeId = '01JFAKE0000000000000000000';
        $headers = $this->tenantHeaders($this->foreignOwner['tenant']);

        $this->getJson("/api/v1/tenant/venues/{$fakeId}", $headers)->assertStatus(404);
        $this->getJson("/api/v1/tenant/venues/{$fakeId}/assets", $headers)->assertStatus(404);
        $this->getJson("/api/v1/tenant/marketplace/rentals/{$fakeId}", $headers)->assertStatus(404);
        $this->getJson("/api/v1/tenant/marketplace/rentals/{$fakeId}/delegation", $headers)->assertStatus(404);
        $this->getJson("/api/v1/tenant/marketplace/statements/{$fakeId}", $headers)->assertStatus(404);
        $this->getJson("/api/v1/tenant/marketplace/disputes/{$fakeId}", $headers)->assertStatus(404);
    }

    // ─── Query-log sampling ──────────────────────────────────────

    public function test_query_log_proves_tenant_predicates_present(): void
    {
        $this->actingAsTenantMember($this->owner['user'], $this->owner['tenant']);
        $headers = $this->tenantHeaders($this->owner['tenant']);

        DB::enableQueryLog();

        $this->getJson('/api/v1/tenant/venues', $headers);
        $this->getJson('/api/v1/tenant/marketplace/rentals', $headers);
        $this->getJson('/api/v1/tenant/marketplace/statements', $headers);

        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $tenantScoped = $queries->filter(
            fn (array $entry): bool => str_contains($entry['query'], 'tenant_id'),
        );
        self::assertGreaterThan(0, $tenantScoped->count(), 'Expected tenant-scoped queries in log.');

        $leaked = $queries->filter(
            fn (array $entry): bool => ! str_contains($entry['query'], 'tenant_id')
                && ! str_contains($entry['query'], 'information_schema')
                && ! str_contains($entry['query'], 'migrations')
                && ! str_contains($entry['query'], 'permissions')
                && ! str_contains($entry['query'], 'personal_access_tokens')
                && ! str_contains($entry['query'], 'sessions')
                && preg_match('/(venues|venue_assets|rental_requests|settlement_statements|marketplace_disputes|control_delegations)/', $entry['query']),
        );
        self::assertCount(0, $leaked->all(), 'Marketplace table queries without tenant_id predicate detected: '.$leaked->pluck('query')->implode(' | '));
    }

    // ─── No cross-tenant data leakage ────────────────────────────

    public function test_catalog_never_reveals_private_venue_fields(): void
    {
        $this->actingAsTenantMember($this->organizer['user'], $this->organizer['tenant']);
        $response = $this->getJson(
            '/api/v1/tenant/marketplace/catalog',
            $this->tenantHeaders($this->organizer['tenant']),
        );
        $response->assertOk();
        $body = $response->content();
        self::assertStringNotContainsString('business_contact_email', $body);
        self::assertStringNotContainsString('private-fixture@example.test', $body);
        self::assertStringNotContainsString('"tenant_id"', $body);
        self::assertStringNotContainsString('"created_by_user_id"', $body);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function grantTenantPermissions(array $fixture, array $keys): void
    {
        $role = DB::table('tenant_roles')->insertGetId([
            'tenant_id' => $fixture['tenant']->id,
            'name' => 'IsolationTest-'.implode(',', $keys).'-'.$fixture['tenant']->id,
            'is_system' => false,
            'created_by_user_id' => $fixture['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionIds = DB::table('permissions')->whereIn('key', $keys)->pluck('id');
        foreach ($permissionIds as $permId) {
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
