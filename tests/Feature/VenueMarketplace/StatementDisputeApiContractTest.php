<?php

namespace Tests\Feature\VenueMarketplace;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\CancelRentalAction;
use App\Modules\VenueMarketplace\Application\Actions\GenerateSettlementStatementAction;
use App\Modules\VenueMarketplace\Application\Actions\OpenMarketplaceDisputeAction;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceDispute;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\SettlementStatement;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class StatementDisputeApiContractTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_tenant_statement_routes_match_the_v1_contract(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->keyBy(fn ($route) => $route->getName());

        foreach ([
            'api.v1.tenant.marketplace.statements.index' => ['GET', 'api/v1/tenant/marketplace/statements'],
            'api.v1.tenant.marketplace.statements.show' => ['GET', 'api/v1/tenant/marketplace/statements/{statement_public_id}'],
            'api.v1.tenant.marketplace.statements.export' => ['GET', 'api/v1/tenant/marketplace/statements/{statement_public_id}/export'],
            'api.v1.tenant.marketplace.statements.disputes.store' => ['POST', 'api/v1/tenant/marketplace/statements/{statement_public_id}/disputes'],
            'api.v1.tenant.marketplace.disputes.show' => ['GET', 'api/v1/tenant/marketplace/disputes/{dispute_public_id}'],
        ] as $name => [$method, $uri]) {
            self::assertTrue($routes->has($name), "Missing route {$name}");
            self::assertContains($method, $routes[$name]->methods());
            self::assertSame($uri, $routes[$name]->uri());
            self::assertContains('auth:sanctum', $routes[$name]->gatherMiddleware());
            self::assertContains('permission:reports.view,tenant', $routes[$name]->gatherMiddleware());
        }

        self::assertContains(
            'idempotency',
            $routes['api.v1.tenant.marketplace.statements.disputes.store']->gatherMiddleware(),
        );
    }

    public function test_platform_marketplace_routes_match_the_v1_contract(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->keyBy(fn ($route) => $route->getName());

        foreach ([
            'api.v1.platform.marketplace.rentals.index' => ['GET', 'api/v1/platform/marketplace/rentals', 'platform.marketplace.view'],
            'api.v1.platform.marketplace.statements.index' => ['GET', 'api/v1/platform/marketplace/statements', 'platform.marketplace.view'],
            'api.v1.platform.marketplace.statements.revisions.store' => ['POST', 'api/v1/platform/marketplace/statements/{statement_public_id}/revisions', 'platform.marketplace.disputes.manage'],
            'api.v1.platform.marketplace.disputes.index' => ['GET', 'api/v1/platform/marketplace/disputes', 'platform.marketplace.disputes.manage'],
            'api.v1.platform.marketplace.disputes.show' => ['GET', 'api/v1/platform/marketplace/disputes/{dispute_public_id}', 'platform.marketplace.disputes.manage'],
            'api.v1.platform.marketplace.disputes.review' => ['POST', 'api/v1/platform/marketplace/disputes/{dispute_public_id}/review', 'platform.marketplace.disputes.manage'],
            'api.v1.platform.marketplace.disputes.notes.store' => ['POST', 'api/v1/platform/marketplace/disputes/{dispute_public_id}/notes', 'platform.marketplace.disputes.manage'],
            'api.v1.platform.marketplace.disputes.resolution.store' => ['POST', 'api/v1/platform/marketplace/disputes/{dispute_public_id}/resolution', 'platform.marketplace.disputes.manage'],
        ] as $name => [$method, $uri, $permission]) {
            self::assertTrue($routes->has($name), "Missing route {$name}");
            self::assertContains($method, $routes[$name]->methods());
            self::assertSame($uri, $routes[$name]->uri());
            self::assertContains('auth:sanctum', $routes[$name]->gatherMiddleware());
            self::assertContains("permission:{$permission},platform", $routes[$name]->gatherMiddleware());
        }

        foreach ([
            'api.v1.platform.marketplace.statements.revisions.store',
            'api.v1.platform.marketplace.disputes.review',
            'api.v1.platform.marketplace.disputes.notes.store',
            'api.v1.platform.marketplace.disputes.resolution.store',
        ] as $mutating) {
            self::assertContains(
                'idempotency',
                $routes[$mutating]->gatherMiddleware(),
                "{$mutating} must enforce idempotency.",
            );
        }
    }

    public function test_tenant_statement_list_returns_paginated_response(): void
    {
        [$owner, $organizer, $statement] = $this->issuedStatement('api-list');
        $this->seed(PermissionSeeder::class);
        $this->grantTenantReportsPermission($owner);
        $this->actingAs($owner['user'], 'sanctum');

        $response = $this->withHeaders($this->tenantHeaders($owner['tenant']))
            ->getJson('/api/v1/tenant/marketplace/statements');

        $response->assertSuccessful();
        $data = $response->json('data');
        self::assertIsArray($data);
        self::assertNotEmpty($data);
        $response->assertJsonStructure(['data', 'meta' => ['page_size', 'has_more', 'next_cursor']]);
    }

    public function test_tenant_statement_show_returns_complete_resource(): void
    {
        [$owner, $organizer, $statement] = $this->issuedStatement('api-show');
        $this->seed(PermissionSeeder::class);
        $this->grantTenantReportsPermission($owner);
        $this->actingAs($owner['user'], 'sanctum');

        $response = $this->withHeaders($this->tenantHeaders($owner['tenant']))
            ->getJson("/api/v1/tenant/marketplace/statements/{$statement->public_id}");

        $response->assertSuccessful();
        $response->assertJsonStructure(['data' => [
            'public_id', 'statement_number', 'revision', 'status', 'dispute_status',
            'rental_outcome', 'agreed_start_at', 'agreed_end_at', 'currency',
            'agreed_total_minor', 'funds_moved', 'lines', 'issued_at',
        ]]);
        $response->assertJsonPath('data.funds_moved', false);
        $response->assertJsonMissing(['id', 'tenant_id', 'organizer_tenant_id']);
    }

    public function test_tenant_statement_export_returns_csv_download(): void
    {
        [$owner, $organizer, $statement] = $this->issuedStatement('api-export');
        $this->seed(PermissionSeeder::class);
        $this->grantTenantReportsPermission($owner);
        $this->actingAs($owner['user'], 'sanctum');

        $response = $this->withHeaders($this->tenantHeaders($owner['tenant']))
            ->getJson("/api/v1/tenant/marketplace/statements/{$statement->public_id}/export");

        $response->assertSuccessful();
        self::assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_tenant_open_dispute_returns_201_with_dispute_resource(): void
    {
        [$owner, $organizer, $statement] = $this->issuedStatement('api-dispute-open');
        $this->seed(PermissionSeeder::class);
        $this->grantTenantReportsPermission($owner);
        $this->actingAs($owner['user'], 'sanctum');

        $response = $this->withHeaders(array_merge(
            $this->tenantHeaders($owner['tenant']),
            ['Idempotency-Key' => 'api-dispute-open-idem'],
        ))->postJson(
            "/api/v1/tenant/marketplace/statements/{$statement->public_id}/disputes",
            [
                'reason_code' => 'billing_error',
                'reason' => 'The kiosk unit count is incorrect.',
            ],
        );

        $response->assertStatus(201);
        $response->assertJsonStructure(['data' => [
            'public_id', 'status', 'reason_code', 'reason', 'opened_at', 'events',
        ]]);
        $response->assertJsonPath('data.status', 'open');
        $response->assertJsonMissing(['tenant_id', 'organizer_tenant_id', 'settlement_statement_id']);
    }

    public function test_tenant_dispute_show_returns_participant_visible_events_only(): void
    {
        [$owner, $organizer, $statement, $dispute] = $this->openedDispute('api-dispute-show');
        $platformUser = User::factory()->create();

        app(\App\Modules\VenueMarketplace\Application\Actions\AddMarketplaceDisputeNoteAction::class)->execute(
            (int) $platformUser->id,
            $dispute->public_id,
            'Internal investigation note.',
            'platform_only',
            'api-dispute-show-note-idem',
            'api-dispute-show-note-corr',
        );

        $this->seed(PermissionSeeder::class);
        $this->grantTenantReportsPermission($owner);
        $this->actingAs($owner['user'], 'sanctum');

        $response = $this->withHeaders($this->tenantHeaders($owner['tenant']))
            ->getJson("/api/v1/tenant/marketplace/disputes/{$dispute->public_id}");

        $response->assertSuccessful();
        $events = $response->json('data.events');
        foreach ($events as $event) {
            self::assertSame('participants', $event['visibility']);
        }
    }

    public function test_platform_dispute_list_returns_all_events_including_platform_only(): void
    {
        [$owner, $organizer, $statement, $dispute] = $this->openedDispute('api-plat-list');
        $this->seed(PermissionSeeder::class);
        $platformUser = User::factory()->create();
        $this->grantPlatformPermission($platformUser, 'platform.marketplace.disputes.manage');

        app(\App\Modules\VenueMarketplace\Application\Actions\AddMarketplaceDisputeNoteAction::class)->execute(
            (int) $platformUser->id,
            $dispute->public_id,
            'Platform internal note for list test.',
            'platform_only',
            'api-plat-list-note-idem',
            'api-plat-list-note-corr',
        );

        $this->actingAs($platformUser, 'sanctum');
        $response = $this->getJson('/api/v1/platform/marketplace/disputes');

        $response->assertSuccessful();
        $disputes = $response->json('data');
        self::assertNotEmpty($disputes);

        $found = collect($disputes)->firstWhere('public_id', $dispute->public_id);
        self::assertNotNull($found);
        $visibilities = collect($found['events'])->pluck('visibility')->unique()->values()->all();
        self::assertContains('platform_only', $visibilities);
    }

    public function test_platform_dispute_show_returns_platform_scoped_events(): void
    {
        [$owner, $organizer, $statement, $dispute] = $this->openedDispute('api-plat-show');
        $this->seed(PermissionSeeder::class);
        $platformUser = User::factory()->create();
        $this->grantPlatformPermission($platformUser, 'platform.marketplace.disputes.manage');

        $this->actingAs($platformUser, 'sanctum');
        $response = $this->getJson("/api/v1/platform/marketplace/disputes/{$dispute->public_id}");

        $response->assertSuccessful();
        $response->assertJsonStructure(['data' => [
            'public_id', 'status', 'reason_code', 'reason',
            'resolution_code', 'resolution_summary', 'opened_at', 'events',
        ]]);
    }

    public function test_platform_revise_statement_returns_201(): void
    {
        [$owner, $organizer, $statement, $dispute] = $this->openedDispute('api-plat-revise');
        $this->seed(PermissionSeeder::class);
        $platformUser = User::factory()->create();
        $this->grantPlatformPermission($platformUser, 'platform.marketplace.disputes.manage');

        $this->actingAs($platformUser, 'sanctum');
        $response = $this->withHeaders(['Idempotency-Key' => 'api-plat-revise-idem'])
            ->postJson(
                "/api/v1/platform/marketplace/statements/{$statement->public_id}/revisions",
                [
                    'dispute_public_id' => $dispute->public_id,
                    'reason_code' => 'factual_correction',
                    'lines' => [],
                ],
            );

        $response->assertStatus(201);
        $response->assertJsonStructure(['data' => [
            'public_id', 'statement_number', 'revision', 'status',
            'currency', 'agreed_total_minor', 'lines',
        ]]);
        self::assertSame(2, $response->json('data.revision'));
    }

    public function test_platform_dispute_resolution_returns_resolved_state(): void
    {
        [$owner, $organizer, $statement, $dispute] = $this->openedDispute('api-plat-resolve');
        $this->seed(PermissionSeeder::class);
        $platformUser = User::factory()->create();
        $this->grantPlatformPermission($platformUser, 'platform.marketplace.disputes.manage');

        $this->actingAs($platformUser, 'sanctum');
        $response = $this->withHeaders(['Idempotency-Key' => 'api-plat-resolve-idem'])
            ->postJson(
                "/api/v1/platform/marketplace/disputes/{$dispute->public_id}/resolution",
                [
                    'decision' => 'resolve',
                    'resolution_code' => 'confirmed_error',
                    'resolution_summary' => 'The billing error has been confirmed.',
                ],
            );

        $response->assertSuccessful();
        $response->assertJsonPath('data.status', 'resolved');
    }

    public function test_platform_start_review_returns_under_review_state(): void
    {
        [$owner, $organizer, $statement, $dispute] = $this->openedDispute('api-plat-review');
        $this->seed(PermissionSeeder::class);
        $platformUser = User::factory()->create();
        $this->grantPlatformPermission($platformUser, 'platform.marketplace.disputes.manage');

        $this->actingAs($platformUser, 'sanctum');
        $response = $this->withHeaders(['Idempotency-Key' => 'api-plat-review-idem'])
            ->postJson("/api/v1/platform/marketplace/disputes/{$dispute->public_id}/review");

        $response->assertSuccessful();
        $response->assertJsonPath('data.status', 'under_review');
    }

    public function test_platform_add_note_returns_201(): void
    {
        [$owner, $organizer, $statement, $dispute] = $this->openedDispute('api-plat-note');
        $this->seed(PermissionSeeder::class);
        $platformUser = User::factory()->create();
        $this->grantPlatformPermission($platformUser, 'platform.marketplace.disputes.manage');

        $this->actingAs($platformUser, 'sanctum');
        $response = $this->withHeaders(['Idempotency-Key' => 'api-plat-note-idem'])
            ->postJson(
                "/api/v1/platform/marketplace/disputes/{$dispute->public_id}/notes",
                [
                    'note' => 'Investigation in progress.',
                    'visibility' => 'participants',
                ],
            );

        $response->assertStatus(201);
    }

    /**
     * @return array{0:array,1:array,2:SettlementStatement}
     */
    private function issuedStatement(string $key): array
    {
        $this->freezeMarketplaceClock();
        $organizer = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        $event = $this->createMarketplaceEvent($organizer['tenant'], $organizer['user']);
        $owner = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $inventory = $this->createPublishedMarketplaceInventory($owner, $key);
        app(TenantContextStore::class)->clear();
        $pubId = $inventory['assets'][3]->publications()->where('status', 'active')->value('public_id');
        $rental = $this->createSubmittedMarketplaceRental($organizer, $event, [$pubId], "{$key}-rental");

        app(CancelRentalAction::class)->execute(
            (int) $organizer['tenant']->id,
            (int) $organizer['user']->id,
            $rental->public_id,
            1,
            "{$key}-cancel",
        );

        $statement = app(GenerateSettlementStatementAction::class)->execute(
            (int) $owner['tenant']->id,
            $rental->public_id,
            "{$key}-gen",
        );

        return [$owner, $organizer, $statement];
    }

    /**
     * @return array{0:array,1:array,2:SettlementStatement,3:MarketplaceDispute}
     */
    private function openedDispute(string $key): array
    {
        [$owner, $organizer, $statement] = $this->issuedStatement($key);

        $dispute = app(OpenMarketplaceDisputeAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $statement->public_id,
            'billing_error',
            'API contract dispute fixture.',
            "{$key}-dispute-idem",
            "{$key}-dispute-corr",
        );

        return [$owner, $organizer, $statement, $dispute];
    }

    private function grantTenantReportsPermission(array $fixture): void
    {
        $role = TenantRole::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'name' => 'Reports viewer',
            'is_system' => false,
            'created_by_user_id' => $fixture['user']->id,
        ]);
        DB::table('tenant_role_permissions')->insert([
            'tenant_id' => $fixture['tenant']->id,
            'tenant_role_id' => $role->id,
            'permission_id' => DB::table('permissions')->where('key', 'reports.view')->value('id'),
            'granted_by_user_id' => $fixture['user']->id,
            'created_at' => now(),
        ]);
        DB::table('tenant_role_assignments')->insert([
            'tenant_id' => $fixture['tenant']->id,
            'tenant_membership_id' => $fixture['membership']->id,
            'tenant_role_id' => $role->id,
            'granted_by_user_id' => $fixture['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantPlatformPermission(User $user, string $permission): void
    {
        $role = PlatformRole::query()->create([
            'name' => "Platform {$permission} role",
            'is_system' => false,
            'created_by_user_id' => $user->id,
        ]);
        DB::table('platform_role_permissions')->insert([
            'platform_role_id' => $role->id,
            'permission_id' => DB::table('permissions')->where('key', $permission)->value('id'),
            'granted_by_user_id' => $user->id,
            'created_at' => now(),
        ]);
        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->id,
            'platform_role_id' => $role->id,
            'granted_by_user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
