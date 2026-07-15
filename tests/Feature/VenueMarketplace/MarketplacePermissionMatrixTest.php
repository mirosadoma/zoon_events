<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\AccessControl\Application\Contracts\DelegatedAcsAssetPort;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
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
use Database\Seeders\FoundationSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

class MarketplacePermissionMatrixTest extends TestCase
{
    use BuildsTenantFixtures;
    use RefreshDatabase;

    private const TENANT_KEYS = [
        'venue.manage', 'marketplace.manage', 'rentals.approve', 'reports.view', 'audit.view',
    ];

    private const PLATFORM_KEYS = [
        'platform.marketplace.view', 'platform.marketplace.disputes.manage',
    ];

    private const PHASE6_API_ROUTES = [
        'api.v1.tenant.venues.index',
        'api.v1.tenant.venues.store',
        'api.v1.tenant.venues.show',
        'api.v1.tenant.venues.update',
        'api.v1.tenant.venues.archive',
        'api.v1.tenant.venues.status',
        'api.v1.tenant.venue-assets.index',
        'api.v1.tenant.venue-assets.store',
        'api.v1.tenant.venue-assets.show',
        'api.v1.tenant.venue-assets.update',
        'api.v1.tenant.venue-assets.availability.replace',
        'api.v1.tenant.venue-assets.publication.publish',
        'api.v1.tenant.venue-assets.publication.withdraw',
        'api.v1.tenant.marketplace.catalog.index',
        'api.v1.tenant.marketplace.catalog.show',
        'api.v1.tenant.marketplace.quotes.store',
        'api.v1.tenant.marketplace.rentals.index',
        'api.v1.tenant.marketplace.rentals.store',
        'api.v1.tenant.marketplace.rentals.show',
        'api.v1.tenant.marketplace.rentals.approve',
        'api.v1.tenant.marketplace.rentals.reject',
        'api.v1.tenant.marketplace.rentals.cancel',
        'api.v1.tenant.marketplace.rentals.revoke',
        'api.v1.tenant.marketplace.rentals.delegation.show',
        'api.v1.tenant.marketplace.statements.index',
        'api.v1.tenant.marketplace.statements.show',
        'api.v1.tenant.marketplace.statements.export',
        'api.v1.tenant.marketplace.statements.disputes.store',
        'api.v1.tenant.marketplace.disputes.show',
        'api.v1.platform.marketplace.rentals.index',
        'api.v1.platform.marketplace.statements.index',
        'api.v1.platform.marketplace.statements.revisions.store',
        'api.v1.platform.marketplace.disputes.index',
        'api.v1.platform.marketplace.disputes.show',
        'api.v1.platform.marketplace.disputes.review',
        'api.v1.platform.marketplace.disputes.notes.store',
        'api.v1.platform.marketplace.disputes.resolution.store',
    ];

    private const PHASE6_WEB_ROUTES = [
        'tenant.venues.index',
        'tenant.venues.show',
        'tenant.marketplace.index',
        'tenant.marketplace.rentals.index',
        'tenant.marketplace.rentals.show',
        'tenant.marketplace.statements.index',
        'tenant.marketplace.statements.show',
        'platform.marketplace.index',
        'platform.marketplace.disputes.show',
    ];

    private const ROUTE_PERMISSION_MAP = [
        'api.v1.tenant.venues.index' => 'venue.manage',
        'api.v1.tenant.venues.store' => 'venue.manage',
        'api.v1.tenant.venues.show' => 'venue.manage',
        'api.v1.tenant.venues.update' => 'venue.manage',
        'api.v1.tenant.venues.archive' => 'venue.manage',
        'api.v1.tenant.venues.status' => 'venue.manage',
        'api.v1.tenant.venue-assets.index' => 'venue.manage',
        'api.v1.tenant.venue-assets.store' => 'venue.manage',
        'api.v1.tenant.venue-assets.show' => 'venue.manage',
        'api.v1.tenant.venue-assets.update' => 'venue.manage',
        'api.v1.tenant.venue-assets.availability.replace' => 'venue.manage',
        'api.v1.tenant.venue-assets.publication.publish' => 'venue.manage',
        'api.v1.tenant.venue-assets.publication.withdraw' => 'venue.manage',
        'api.v1.tenant.marketplace.catalog.index' => 'marketplace.manage',
        'api.v1.tenant.marketplace.catalog.show' => 'marketplace.manage',
        'api.v1.tenant.marketplace.quotes.store' => 'marketplace.manage',
        'api.v1.tenant.marketplace.rentals.index' => 'marketplace.manage',
        'api.v1.tenant.marketplace.rentals.store' => 'marketplace.manage',
        'api.v1.tenant.marketplace.rentals.show' => 'marketplace.manage',
        'api.v1.tenant.marketplace.rentals.approve' => 'rentals.approve',
        'api.v1.tenant.marketplace.rentals.reject' => 'rentals.approve',
        'api.v1.tenant.marketplace.rentals.cancel' => 'marketplace.manage',
        'api.v1.tenant.marketplace.rentals.revoke' => 'rentals.approve',
        'api.v1.tenant.marketplace.rentals.delegation.show' => 'marketplace.manage',
        'api.v1.tenant.marketplace.statements.index' => 'reports.view',
        'api.v1.tenant.marketplace.statements.show' => 'reports.view',
        'api.v1.tenant.marketplace.statements.export' => 'reports.view',
        'api.v1.tenant.marketplace.statements.disputes.store' => 'reports.view',
        'api.v1.tenant.marketplace.disputes.show' => 'reports.view',
        'api.v1.platform.marketplace.rentals.index' => 'platform.marketplace.view',
        'api.v1.platform.marketplace.statements.index' => 'platform.marketplace.view',
        'api.v1.platform.marketplace.statements.revisions.store' => 'platform.marketplace.disputes.manage',
        'api.v1.platform.marketplace.disputes.index' => 'platform.marketplace.disputes.manage',
        'api.v1.platform.marketplace.disputes.show' => 'platform.marketplace.disputes.manage',
        'api.v1.platform.marketplace.disputes.review' => 'platform.marketplace.disputes.manage',
        'api.v1.platform.marketplace.disputes.notes.store' => 'platform.marketplace.disputes.manage',
        'api.v1.platform.marketplace.disputes.resolution.store' => 'platform.marketplace.disputes.manage',
    ];

    // ─── Original T244 core tests ────────────────────────────────

    public function test_phase_six_catalog_contains_exactly_scoped_permissions(): void
    {
        $definitions = collect(PermissionSeeder::definitions())->keyBy('key');

        foreach (self::TENANT_KEYS as $key) {
            self::assertSame('tenant', $definitions->get($key)['scope'] ?? null, $key);
        }
        foreach (self::PLATFORM_KEYS as $key) {
            self::assertSame('platform', $definitions->get($key)['scope'] ?? null, $key);
        }
    }

    public function test_marketplace_permissions_deny_by_default_and_scopes_do_not_cross(): void
    {
        $this->seed(PermissionSeeder::class);
        $fixture = $this->createTenantMember();
        $context = app(TenantContextStore::class)->bind($fixture['tenant'], $fixture['membership'], $fixture['user']);
        $evaluator = app(PermissionEvaluator::class);

        foreach (self::TENANT_KEYS as $key) {
            self::assertFalse($evaluator->hasTenantPermission($context, $key), $key);
            self::assertFalse($evaluator->hasPlatformPermission($fixture['user'], $key), $key);
        }
        foreach (self::PLATFORM_KEYS as $key) {
            self::assertFalse($evaluator->hasTenantPermission($context, $key), $key);
            self::assertFalse($evaluator->hasPlatformPermission($fixture['user'], $key), $key);
        }

        $tenantRole = TenantRole::query()->create([
            'tenant_id' => $fixture['tenant']->id, 'name' => 'Marketplace tenant',
            'is_system' => false, 'created_by_user_id' => $fixture['user']->id,
        ]);
        foreach (DB::table('permissions')->whereIn('key', [...self::TENANT_KEYS, ...self::PLATFORM_KEYS])->get() as $permission) {
            DB::table('tenant_role_permissions')->insert([
                'tenant_id' => $fixture['tenant']->id, 'tenant_role_id' => $tenantRole->id,
                'permission_id' => $permission->id, 'granted_by_user_id' => $fixture['user']->id,
                'created_at' => now(),
            ]);
        }
        DB::table('tenant_role_assignments')->insert([
            'tenant_id' => $fixture['tenant']->id, 'tenant_membership_id' => $fixture['membership']->id,
            'tenant_role_id' => $tenantRole->id, 'granted_by_user_id' => $fixture['user']->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (self::TENANT_KEYS as $key) {
            self::assertTrue($evaluator->hasTenantPermission($context, $key), $key);
        }
        foreach (self::PLATFORM_KEYS as $key) {
            self::assertFalse($evaluator->hasTenantPermission($context, $key), $key);
        }

        $platformRole = PlatformRole::query()->create([
            'name' => 'Marketplace platform', 'is_system' => false,
            'created_by_user_id' => $fixture['user']->id,
        ]);
        foreach (DB::table('permissions')->whereIn('key', self::PLATFORM_KEYS)->pluck('id') as $permissionId) {
            DB::table('platform_role_permissions')->insert([
                'platform_role_id' => $platformRole->id, 'permission_id' => $permissionId,
                'granted_by_user_id' => $fixture['user']->id, 'created_at' => now(),
            ]);
        }
        DB::table('platform_role_assignments')->insert([
            'user_id' => $fixture['user']->id, 'platform_role_id' => $platformRole->id,
            'granted_by_user_id' => $fixture['user']->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (self::PLATFORM_KEYS as $key) {
            self::assertTrue($evaluator->hasPlatformPermission($fixture['user'], $key), $key);
        }
        foreach (self::TENANT_KEYS as $key) {
            self::assertFalse($evaluator->hasPlatformPermission($fixture['user'], $key), $key);
        }
    }

    public function test_foundation_role_matrix_is_idempotent_and_least_privilege(): void
    {
        $this->seed(FoundationSeeder::class);
        $this->seed(FoundationSeeder::class);

        $expected = [
            'Venue Owner Admin' => ['venue.manage', 'rentals.approve', 'reports.view', 'audit.view'],
            'Venue Asset Manager' => ['venue.manage'],
            'Venue Rental Approver' => ['rentals.approve'],
            'Venue Finance Manager' => ['reports.view', 'audit.view'],
        ];

        foreach ($expected as $roleName => $keys) {
            $role = TenantRole::query()->withoutGlobalScopes()->where('name', $roleName)->firstOrFail();
            self::assertEqualsCanonicalizing($keys, $role->permissions()->pluck('key')->all(), $roleName);
        }
    }

    // ─── T244: Route-to-permission coverage ──────────────────────

    public function test_every_phase6_api_route_has_permission_middleware(): void
    {
        foreach (self::PHASE6_API_ROUTES as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            self::assertNotNull($route, "Route {$routeName} is not registered.");

            $middlewares = collect($route->gatherMiddleware());
            $hasPermission = $middlewares->contains(
                fn (string $m): bool => str_starts_with($m, 'permission:'),
            );
            self::assertTrue($hasPermission, "Route {$routeName} lacks permission middleware.");
        }
    }

    public function test_every_phase6_api_route_maps_to_expected_permission(): void
    {
        foreach (self::ROUTE_PERMISSION_MAP as $routeName => $expectedPermission) {
            $route = Route::getRoutes()->getByName($routeName);
            self::assertNotNull($route, "Route {$routeName} is not registered.");

            $permissionMiddleware = collect($route->gatherMiddleware())
                ->first(fn (string $m): bool => str_starts_with($m, 'permission:'));
            self::assertNotNull($permissionMiddleware, "No permission middleware on {$routeName}.");

            preg_match('/^permission:([^,]+)/', $permissionMiddleware, $match);
            self::assertSame(
                $expectedPermission,
                $match[1] ?? '',
                "Route {$routeName} expected {$expectedPermission} but found ".($match[1] ?? 'none'),
            );
        }
    }

    public function test_phase6_web_routes_exist(): void
    {
        foreach (self::PHASE6_WEB_ROUTES as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            self::assertNotNull($route, "Web route {$routeName} is not registered.");
        }
    }

    // ─── T244: Organization type permission behaviour ────────────

    public function test_venue_owner_org_gets_venue_permissions_only(): void
    {
        $this->seed(FoundationSeeder::class);
        $this->registerFakes();

        $owner = $this->createTenantMember([], ['organization_type' => 'venue_owner']);
        $this->grantRolePermissions($owner, ['venue.manage']);
        $this->actingAsTenantMember($owner['user'], $owner['tenant']);

        $this->getJson('/api/v1/tenant/venues', $this->tenantHeaders($owner['tenant']))
            ->assertOk();

        $this->getJson('/api/v1/tenant/marketplace/catalog', $this->tenantHeaders($owner['tenant']))
            ->assertForbidden();
    }

    public function test_organizer_org_gets_marketplace_permissions_only(): void
    {
        $this->seed(FoundationSeeder::class);
        $this->registerFakes();

        $org = $this->createTenantMember([], ['organization_type' => 'organizer']);
        $this->grantRolePermissions($org, ['marketplace.manage']);
        $this->actingAsTenantMember($org['user'], $org['tenant']);

        $this->getJson('/api/v1/tenant/marketplace/catalog', $this->tenantHeaders($org['tenant']))
            ->assertOk();

        $this->getJson('/api/v1/tenant/venues', $this->tenantHeaders($org['tenant']))
            ->assertForbidden();
    }

    public function test_hybrid_org_can_access_both_venue_and_marketplace(): void
    {
        $this->seed(FoundationSeeder::class);
        $this->registerFakes();

        $hybrid = $this->createTenantMember([], ['organization_type' => 'hybrid']);
        $this->grantRolePermissions($hybrid, ['venue.manage', 'marketplace.manage']);
        $this->actingAsTenantMember($hybrid['user'], $hybrid['tenant']);

        $this->getJson('/api/v1/tenant/venues', $this->tenantHeaders($hybrid['tenant']))
            ->assertOk();
        $this->getJson('/api/v1/tenant/marketplace/catalog', $this->tenantHeaders($hybrid['tenant']))
            ->assertOk();
    }

    // ─── T244: Delegated permission never grants base perms ──────

    public function test_delegation_never_grants_base_tenant_permissions(): void
    {
        $this->seed(FoundationSeeder::class);
        $this->registerFakes();

        $org = $this->createTenantMember([], ['organization_type' => 'organizer']);
        $this->actingAsTenantMember($org['user'], $org['tenant']);

        $context = app(TenantContextStore::class)->bind($org['tenant'], $org['membership'], $org['user']);
        $evaluator = app(PermissionEvaluator::class);

        foreach (self::TENANT_KEYS as $key) {
            self::assertFalse(
                $evaluator->hasTenantPermission($context, $key),
                "Delegation must not implicitly grant '{$key}'.",
            );
        }
    }

    // ─── T244: Platform view/manage split ────────────────────────

    public function test_platform_view_cannot_manage_disputes(): void
    {
        $this->seed(FoundationSeeder::class);
        $fixture = $this->createTenantMember();
        $this->actingAsTenantMember($fixture['user'], $fixture['tenant']);
        $this->grantPlatformRole($fixture, ['platform.marketplace.view']);

        $this->getJson('/api/v1/platform/marketplace/rentals')
            ->assertOk();
        $this->getJson('/api/v1/platform/marketplace/disputes')
            ->assertForbidden();
    }

    public function test_platform_manage_can_access_disputes(): void
    {
        $this->seed(FoundationSeeder::class);
        $fixture = $this->createTenantMember();
        $this->actingAsTenantMember($fixture['user'], $fixture['tenant']);
        $this->grantPlatformRole($fixture, ['platform.marketplace.disputes.manage']);

        $this->getJson('/api/v1/platform/marketplace/disputes')
            ->assertOk();
    }

    // ─── T244: Wildcard/edge cases ───────────────────────────────

    public function test_unauthenticated_request_returns_401_on_all_api_routes(): void
    {
        foreach (self::PHASE6_API_ROUTES as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            if ($route === null) {
                continue;
            }

            $uri = '/'.ltrim($route->uri(), '/');
            $uri = preg_replace('/\{[^}]+\}/', '01JFAKEULID00000000000000', $uri);
            $method = strtolower(collect($route->methods())->first(fn (string $m): bool => $m !== 'HEAD') ?? 'GET');

            $response = $this->json($method, $uri);
            self::assertContains(
                $response->status(),
                [401, 302],
                "Route {$routeName} should reject unauthenticated requests, got {$response->status()}.",
            );
        }
    }

    public function test_deny_by_default_remains_true_for_new_member(): void
    {
        $this->seed(FoundationSeeder::class);
        $this->registerFakes();

        $member = $this->createTenantMember([], ['organization_type' => 'hybrid']);
        $this->actingAsTenantMember($member['user'], $member['tenant']);
        $headers = $this->tenantHeaders($member['tenant']);

        $this->getJson('/api/v1/tenant/venues', $headers)->assertForbidden();
        $this->getJson('/api/v1/tenant/marketplace/catalog', $headers)->assertForbidden();
        $this->getJson('/api/v1/tenant/marketplace/rentals', $headers)->assertForbidden();
        $this->getJson('/api/v1/tenant/marketplace/statements', $headers)->assertForbidden();
    }

    // ─── T244: Seeded role permission coverage ───────────────────

    public function test_seeded_roles_never_include_platform_permissions(): void
    {
        $this->seed(FoundationSeeder::class);

        $seededRoles = TenantRole::query()->withoutGlobalScopes()->where('is_system', true)->get();

        foreach ($seededRoles as $role) {
            $permissions = $role->permissions()->pluck('key')->all();
            foreach ($permissions as $key) {
                self::assertStringNotStartsWith(
                    'platform.',
                    $key,
                    "Seeded tenant role '{$role->name}' must not contain platform permission '{$key}'.",
                );
            }
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function registerFakes(): void
    {
        app()->instance(OrganizationEligibility::class, new FakeOrganizationEligibility);
        app()->instance(MarketplaceEventReader::class, new FakeMarketplaceEventReader);
        app()->instance(DelegatedAcsAssetPort::class, new FakeDelegatedAcsAssetPort);
        app()->instance(DelegatedKioskAssetPort::class, new FakeDelegatedKioskAssetPort);
        app()->instance(DelegatedPrinterAssetPort::class, new FakeDelegatedPrinterAssetPort);
        app()->instance(DelegatedScannerAssetPort::class, new FakeDelegatedScannerAssetPort);
    }

    private function grantRolePermissions(array $fixture, array $keys): void
    {
        $role = DB::table('tenant_roles')->insertGetId([
            'tenant_id' => $fixture['tenant']->id,
            'name' => 'T244-'.implode('-', $keys).'-'.$fixture['tenant']->id,
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

    private function grantPlatformRole(array $fixture, array $keys): void
    {
        $platformRole = PlatformRole::query()->create([
            'name' => 'T244-platform-'.implode('-', $keys),
            'is_system' => false,
            'created_by_user_id' => $fixture['user']->id,
        ]);
        foreach (DB::table('permissions')->whereIn('key', $keys)->pluck('id') as $permId) {
            DB::table('platform_role_permissions')->insert([
                'platform_role_id' => $platformRole->id,
                'permission_id' => $permId,
                'granted_by_user_id' => $fixture['user']->id,
                'created_at' => now(),
            ]);
        }
        DB::table('platform_role_assignments')->insert([
            'user_id' => $fixture['user']->id,
            'platform_role_id' => $platformRole->id,
            'granted_by_user_id' => $fixture['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
