<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\ApproveRentalAction;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class RentalDecisionApiContractTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_decision_routes_match_the_v1_contract(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->keyBy(fn ($route) => $route->getName());

        foreach ([
            'api.v1.tenant.marketplace.rentals.approve' => ['POST', 'api/v1/tenant/marketplace/rentals/{rental_public_id}/approve'],
            'api.v1.tenant.marketplace.rentals.reject' => ['POST', 'api/v1/tenant/marketplace/rentals/{rental_public_id}/reject'],
            'api.v1.tenant.marketplace.rentals.cancel' => ['POST', 'api/v1/tenant/marketplace/rentals/{rental_public_id}/cancel'],
            'api.v1.tenant.marketplace.rentals.revoke' => ['POST', 'api/v1/tenant/marketplace/rentals/{rental_public_id}/revoke'],
        ] as $name => [$method, $uri]) {
            self::assertTrue($routes->has($name), "Missing route {$name}");
            self::assertContains($method, $routes[$name]->methods());
            self::assertSame($uri, $routes[$name]->uri());
            self::assertContains('auth:sanctum', $routes[$name]->gatherMiddleware());
            self::assertContains('idempotency', $routes[$name]->gatherMiddleware());
        }

        foreach (['approve', 'reject', 'revoke'] as $action) {
            self::assertContains(
                'permission:rentals.approve,tenant',
                $routes["api.v1.tenant.marketplace.rentals.{$action}"]->gatherMiddleware(),
                "{$action} must require rentals.approve permission.",
            );
        }

        self::assertContains(
            'permission:marketplace.manage,tenant',
            $routes['api.v1.tenant.marketplace.rentals.cancel']->gatherMiddleware(),
            'cancel must require marketplace.manage permission.',
        );
    }

    public function test_approve_validates_expected_version_is_required_integer(): void
    {
        [$owner, , $rental] = $this->authedOwnerRental('contract-approve-val');

        $this->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/approve", [], [
            'Idempotency-Key' => 'contract-approve-no-version',
        ])->assertUnprocessable()->assertJsonValidationErrors(['expected_version']);

        $this->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/approve", [
            'expected_version' => 0,
        ], ['Idempotency-Key' => 'contract-approve-zero-version'])
            ->assertUnprocessable()->assertJsonValidationErrors(['expected_version']);

        $this->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/approve", [
            'expected_version' => 'abc',
        ], ['Idempotency-Key' => 'contract-approve-string-version'])
            ->assertUnprocessable()->assertJsonValidationErrors(['expected_version']);
    }

    public function test_reject_validates_expected_version_and_reason(): void
    {
        [$owner, , $rental] = $this->authedOwnerRental('contract-reject-val');

        $this->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/reject", [], [
            'Idempotency-Key' => 'contract-reject-missing',
        ])->assertUnprocessable()->assertJsonValidationErrors(['expected_version', 'reason']);

        $this->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/reject", [
            'expected_version' => 1,
            'reason' => '',
        ], ['Idempotency-Key' => 'contract-reject-empty-reason'])
            ->assertUnprocessable()->assertJsonValidationErrors(['reason']);

        $this->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/reject", [
            'expected_version' => 1,
            'reason' => str_repeat('x', 2001),
        ], ['Idempotency-Key' => 'contract-reject-long-reason'])
            ->assertUnprocessable()->assertJsonValidationErrors(['reason']);
    }

    public function test_cancel_accepts_optional_reason(): void
    {
        [, $organizer, $rental] = $this->authedOrganizerRental('contract-cancel-reason');

        $this->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/cancel", [
            'expected_version' => 1,
        ], ['Idempotency-Key' => 'contract-cancel-no-reason'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cancel_with_optional_reason_text(): void
    {
        [, $organizer, $rental] = $this->authedOrganizerRental('contract-cancel-with-reason');

        $this->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/cancel", [
            'expected_version' => 1,
            'reason' => 'Event rescheduled',
        ], ['Idempotency-Key' => 'contract-cancel-with-reason'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_revoke_validates_required_reason(): void
    {
        [$owner, , $rental] = $this->authedOwnerRental('contract-revoke-val');

        app(ApproveRentalAction::class)->execute(
            (int) $owner['tenant']->id,
            (int) $owner['user']->id,
            $rental->public_id,
            1,
            'contract-revoke-approve-idempotency',
            'contract-revoke-approve-correlation',
        );

        $this->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/revoke", [
            'expected_version' => 2,
        ], ['Idempotency-Key' => 'contract-revoke-no-reason'])
            ->assertUnprocessable()->assertJsonValidationErrors(['reason']);

        $this->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/revoke", [
            'expected_version' => 2,
            'reason' => '',
        ], ['Idempotency-Key' => 'contract-revoke-empty-reason'])
            ->assertUnprocessable()->assertJsonValidationErrors(['reason']);
    }

    public function test_approve_idempotent_replay_returns_same_result(): void
    {
        [$owner, , $rental] = $this->authedOwnerRental('contract-idem');

        $first = $this->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/approve", [
            'expected_version' => 1,
        ], ['Idempotency-Key' => 'contract-idem-same-key'])->assertOk();

        $replay = $this->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/approve", [
            'expected_version' => 1,
        ], ['Idempotency-Key' => 'contract-idem-same-key'])->assertOk();

        self::assertSame(
            $first->json('data.public_id'),
            $replay->json('data.public_id'),
        );
    }

    public function test_conflict_returns_409_with_reason_code(): void
    {
        [$owner, $organizer, $rentalA, $publicationPublicId, $event] = $this->authedOwnerRental('contract-conflict');
        $rentalB = $this->createSubmittedMarketplaceRental(
            $organizer,
            $event,
            [$publicationPublicId],
            'contract-conflict-second',
        );

        $this->postJson("/api/v1/tenant/marketplace/rentals/{$rentalA->public_id}/approve", [
            'expected_version' => 1,
        ], ['Idempotency-Key' => 'contract-conflict-winner'])->assertOk();

        $this->postJson("/api/v1/tenant/marketplace/rentals/{$rentalB->public_id}/approve", [
            'expected_version' => 1,
        ], ['Idempotency-Key' => 'contract-conflict-loser'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'marketplace_reservation_conflict');
    }

    public function test_stale_version_returns_409_state_conflict(): void
    {
        [$owner, , $rental] = $this->authedOwnerRental('contract-stale');

        $this->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/approve", [
            'expected_version' => 999,
        ], ['Idempotency-Key' => 'contract-stale-version'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'marketplace_rental_state_conflict');
    }

    public function test_participant_safe_response_schema_on_approve(): void
    {
        [$owner, , $rental] = $this->authedOwnerRental('contract-schema');

        $response = $this->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/approve", [
            'expected_version' => 1,
        ], ['Idempotency-Key' => 'contract-schema-approve'])
            ->assertOk();

        $data = $response->json('data');
        self::assertArrayHasKey('public_id', $data);
        self::assertArrayHasKey('status', $data);
        self::assertArrayHasKey('version', $data);
        self::assertArrayHasKey('viewer_role', $data);
        self::assertArrayHasKey('venue', $data);
        self::assertArrayHasKey('submitted_at', $data);
        self::assertArrayHasKey('approved_at', $data);

        self::assertSame('approved', $data['status']);
        self::assertSame('owner', $data['viewer_role']);
        self::assertIsInt($data['version']);
        self::assertSame(2, $data['version']);

        self::assertArrayNotHasKey('tenant_id', $data);
        self::assertArrayNotHasKey('organizer_tenant_id', $data);
        self::assertArrayNotHasKey('idempotency_key_hash', $data);
    }

    public function test_reject_response_schema(): void
    {
        [$owner, , $rental] = $this->authedOwnerRental('contract-reject-schema');

        $response = $this->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/reject", [
            'expected_version' => 1,
            'reason' => 'Venue under renovation',
        ], ['Idempotency-Key' => 'contract-reject-schema-key'])
            ->assertOk();

        $data = $response->json('data');
        self::assertSame('rejected', $data['status']);
        self::assertSame('owner', $data['viewer_role']);
        self::assertSame(2, $data['version']);
        self::assertSame('Venue under renovation', $data['decision_reason']);
    }

    public function test_cancel_response_schema(): void
    {
        [, $organizer, $rental] = $this->authedOrganizerRental('contract-cancel-schema');

        $response = $this->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/cancel", [
            'expected_version' => 1,
        ], ['Idempotency-Key' => 'contract-cancel-schema-key'])
            ->assertOk();

        $data = $response->json('data');
        self::assertSame('cancelled', $data['status']);
        self::assertSame('organizer', $data['viewer_role']);
        self::assertSame(2, $data['version']);
    }

    /**
     * @return array{0:array,1:array,2:RentalRequest,3:string,4:mixed}
     */
    private function authedOwnerRental(string $key): array
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

        $this->grantTenantPermission($owner, 'rentals.approve');
        $this->actingAsTenantMember($owner['user'], $owner['tenant']);
        $this->withHeaders($this->tenantHeaders($owner['tenant']));

        return [$owner, $organizer, $rental, $publicationPublicId, $event];
    }

    /**
     * @return array{0:array,1:array,2:RentalRequest}
     */
    private function authedOrganizerRental(string $key): array
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

        $this->grantTenantPermission($organizer, 'marketplace.manage');
        $this->actingAsTenantMember($organizer['user'], $organizer['tenant']);
        $this->withHeaders($this->tenantHeaders($organizer['tenant']));

        return [$owner, $organizer, $rental];
    }

    private function grantTenantPermission(array $fixture, string $permission): void
    {
        $this->seed(PermissionSeeder::class);
        $role = TenantRole::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'name' => 'Decision contract role '.Str::random(6),
            'is_system' => false,
            'created_by_user_id' => $fixture['user']->id,
        ]);
        DB::table('tenant_role_permissions')->insert([
            'tenant_id' => $fixture['tenant']->id,
            'tenant_role_id' => $role->id,
            'permission_id' => DB::table('permissions')->where('key', $permission)->value('id'),
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
}
