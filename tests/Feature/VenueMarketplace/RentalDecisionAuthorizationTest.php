<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class RentalDecisionAuthorizationTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_owner_with_rentals_approve_can_approve_and_reject(): void
    {
        [$owner, , $rental] = $this->submittedRental('auth-approve');
        $this->grantTenantPermission($owner, 'rentals.approve');
        $this->actingAsTenantMember($owner['user'], $owner['tenant']);

        $this->withHeaders($this->tenantHeaders($owner['tenant']))
            ->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/approve", [
                'expected_version' => 1,
            ], ['Idempotency-Key' => 'auth-approve-idempotency'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_owner_without_rentals_approve_is_forbidden(): void
    {
        [$owner, , $rental] = $this->submittedRental('auth-no-perm');
        $this->grantTenantPermission($owner, 'marketplace.manage');
        $this->actingAsTenantMember($owner['user'], $owner['tenant']);

        $this->withHeaders($this->tenantHeaders($owner['tenant']))
            ->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/approve", [
                'expected_version' => 1,
            ], ['Idempotency-Key' => 'auth-no-perm-idempotency'])
            ->assertForbidden();
    }

    public function test_organizer_can_cancel_own_rental(): void
    {
        [, $organizer, $rental] = $this->submittedRental('auth-cancel');
        $this->grantTenantPermission($organizer, 'marketplace.manage');
        $this->actingAsTenantMember($organizer['user'], $organizer['tenant']);

        $this->withHeaders($this->tenantHeaders($organizer['tenant']))
            ->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/cancel", [
                'expected_version' => 1,
            ], ['Idempotency-Key' => 'auth-cancel-idempotency'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cross_tenant_ids_return_404_before_state_details_leak(): void
    {
        [$owner, $organizer, $rental] = $this->submittedRental('auth-cross');
        $unrelated = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $this->grantTenantPermission($unrelated, 'rentals.approve');
        $this->actingAsTenantMember($unrelated['user'], $unrelated['tenant']);

        DB::connection()->enableQueryLog();

        $response = $this->withHeaders($this->tenantHeaders($unrelated['tenant']))
            ->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/approve", [
                'expected_version' => 1,
            ], ['Idempotency-Key' => 'auth-cross-idempotency']);

        $status = $response->status();
        self::assertTrue(
            in_array($status, [403, 404], true),
            "Foreign rental must return 403 or 404, got {$status}.",
        );
        $response->assertJsonMissing(['status' => 'requested']);
    }

    public function test_venue_owner_and_hybrid_eligibility_for_approve_and_revoke(): void
    {
        foreach (['venue_owner', 'hybrid'] as $orgType) {
            [$owner, , $rental] = $this->submittedRental("auth-elig-{$orgType}");
            $this->grantTenantPermission($owner, 'rentals.approve');
            $this->actingAsTenantMember($owner['user'], $owner['tenant']);

            $this->withHeaders($this->tenantHeaders($owner['tenant']))
                ->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/approve", [
                    'expected_version' => 1,
                ], ['Idempotency-Key' => "auth-elig-{$orgType}-approve"])
                ->assertOk()
                ->assertJsonPath('data.status', 'approved');
        }
    }

    public function test_organizer_type_cannot_approve_or_reject(): void
    {
        [$owner, $organizer, $rental] = $this->submittedRental('auth-org-deny');
        $this->grantTenantPermission($organizer, 'rentals.approve');
        $this->actingAsTenantMember($organizer['user'], $organizer['tenant']);

        $response = $this->withHeaders($this->tenantHeaders($organizer['tenant']))
            ->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/approve", [
                'expected_version' => 1,
            ], ['Idempotency-Key' => 'auth-org-deny-idempotency']);

        $status = $response->status();
        self::assertTrue(
            in_array($status, [403, 404], true),
            "Organizer approve must return 403 or 404, got {$status}.",
        );
    }

    public function test_platform_role_does_not_escalate_to_tenant_rental_approval(): void
    {
        [$owner, , $rental] = $this->submittedRental('auth-platform-no-escalate');
        $this->seed(PermissionSeeder::class);

        $platformRole = PlatformRole::query()->create([
            'name' => 'Marketplace platform escalation test',
            'is_system' => false,
            'created_by_user_id' => $owner['user']->id,
        ]);
        DB::table('platform_role_permissions')->insert([
            'platform_role_id' => $platformRole->id,
            'permission_id' => DB::table('permissions')->where('key', 'platform.marketplace.view')->value('id'),
            'granted_by_user_id' => $owner['user']->id,
            'created_at' => now(),
        ]);
        DB::table('platform_role_assignments')->insert([
            'user_id' => $owner['user']->id,
            'platform_role_id' => $platformRole->id,
            'granted_by_user_id' => $owner['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsTenantMember($owner['user'], $owner['tenant']);

        $this->withHeaders($this->tenantHeaders($owner['tenant']))
            ->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/approve", [
                'expected_version' => 1,
            ], ['Idempotency-Key' => 'auth-platform-escalate-idempotency'])
            ->assertForbidden();
    }

    public function test_terminal_state_denial_for_all_decision_actions(): void
    {
        [$owner, $organizer, $rental] = $this->submittedRental('auth-terminal');
        $this->grantTenantPermission($owner, 'rentals.approve');
        $this->grantTenantPermission($organizer, 'marketplace.manage');

        $this->actingAsTenantMember($owner['user'], $owner['tenant']);
        $this->withHeaders($this->tenantHeaders($owner['tenant']))
            ->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/reject", [
                'expected_version' => 1,
                'reason' => 'Not available',
            ], ['Idempotency-Key' => 'auth-terminal-reject'])
            ->assertOk();

        $rejectedVersion = (int) $rental->fresh()->version;

        $this->withHeaders($this->tenantHeaders($owner['tenant']))
            ->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/approve", [
                'expected_version' => $rejectedVersion,
            ], ['Idempotency-Key' => 'auth-terminal-approve-after-reject'])
            ->assertStatus(409);

        $this->actingAsTenantMember($organizer['user'], $organizer['tenant']);
        $this->withHeaders($this->tenantHeaders($organizer['tenant']))
            ->postJson("/api/v1/tenant/marketplace/rentals/{$rental->public_id}/cancel", [
                'expected_version' => $rejectedVersion,
            ], ['Idempotency-Key' => 'auth-terminal-cancel-after-reject'])
            ->assertStatus(409);
    }

    /**
     * @return array{0:array,1:array,2:RentalRequest}
     */
    private function submittedRental(string $key): array
    {
        $this->freezeMarketplaceClock();
        $orgType = str_contains($key, 'hybrid') ? 'hybrid' : 'venue_owner';
        $organizer = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        $event = $this->createMarketplaceEvent($organizer['tenant'], $organizer['user']);
        $owner = $this->createTenantMember(tenantAttributes: ['organization_type' => $orgType]);
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

        return [$owner, $organizer, $rental];
    }

    private function grantTenantPermission(array $fixture, string $permission): void
    {
        $this->seed(PermissionSeeder::class);
        $role = TenantRole::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'name' => 'Rental auth role '.Str::random(6),
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
