<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\Venue;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

final class OwnerVenueAuthorizationTest extends TestCase
{
    use BuildsTenantFixtures;
    use DatabaseTransactions;

    public function test_organizer_is_denied_even_with_venue_permission(): void
    {
        $fixture = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        $this->grantTenantPermission($fixture, 'venue.manage');

        $this->actingAsTenantMember($fixture['user'], $fixture['tenant']);
        $this->withHeaders($this->tenantHeaders($fixture['tenant']))
            ->getJson('/api/v1/tenant/venues')
            ->assertForbidden()
            ->assertJsonPath('code', 'organization_type_not_eligible');
    }

    public function test_permission_is_required_and_platform_role_does_not_escalate(): void
    {
        $fixture = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $this->seed(PermissionSeeder::class);
        $platformRole = PlatformRole::query()->create([
            'name' => 'Marketplace platform only',
            'is_system' => false,
            'created_by_user_id' => $fixture['user']->id,
        ]);
        DB::table('platform_role_permissions')->insert([
            'platform_role_id' => $platformRole->id,
            'permission_id' => DB::table('permissions')->where('key', 'platform.marketplace.view')->value('id'),
            'granted_by_user_id' => $fixture['user']->id,
            'created_at' => now(),
        ]);
        DB::table('platform_role_assignments')->insert([
            'user_id' => $fixture['user']->id,
            'platform_role_id' => $platformRole->id,
            'granted_by_user_id' => $fixture['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsTenantMember($fixture['user'], $fixture['tenant']);
        $this->withHeaders($this->tenantHeaders($fixture['tenant']))
            ->getJson('/api/v1/tenant/venues')
            ->assertForbidden();
    }

    public function test_foreign_opaque_ids_return_404_and_queries_remain_tenant_scoped(): void
    {
        $owner = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $other = $this->createTenantMember(tenantAttributes: ['organization_type' => 'hybrid']);
        $this->grantTenantPermission($other, 'venue.manage');
        $foreignVenue = $this->venue($owner);

        DB::connection()->enableQueryLog();
        $this->actingAsTenantMember($other['user'], $other['tenant']);
        $this->withHeaders($this->tenantHeaders($other['tenant']))
            ->getJson("/api/v1/tenant/venues/{$foreignVenue->public_id}")
            ->assertNotFound()
            ->assertJsonPath('code', 'marketplace_venue_not_found');

        $queries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'from `venues`'))
            ->pluck('query')
            ->implode("\n");
        self::assertStringContainsString('`venues`.`tenant_id`', $queries);
    }

    public function test_venue_owner_and_hybrid_are_allowed_with_permission(): void
    {
        foreach (['venue_owner', 'hybrid'] as $type) {
            $fixture = $this->createTenantMember(tenantAttributes: ['organization_type' => $type]);
            $this->grantTenantPermission($fixture, 'venue.manage');
            $this->actingAsTenantMember($fixture['user'], $fixture['tenant']);
            $this->withHeaders($this->tenantHeaders($fixture['tenant']))
                ->getJson('/api/v1/tenant/venues')
                ->assertOk();
        }
    }

    private function venue(array $fixture): Venue
    {
        return Venue::query()->forTenant((string) $fixture['tenant']->id)->forceCreate([
            'tenant_id' => $fixture['tenant']->id,
            'public_id' => (string) Str::ulid(),
            'name_en' => 'Private venue',
            'name_ar' => 'موقع خاص',
            'address_en' => 'Private address',
            'address_ar' => 'عنوان خاص',
            'country_code' => 'SA',
            'city_code' => 'riyadh',
            'timezone' => 'Asia/Riyadh',
            'publish_contact' => false,
            'status' => 'draft',
            'version' => 1,
            'created_by_user_id' => $fixture['user']->id,
            'updated_by_user_id' => $fixture['user']->id,
        ]);
    }

    private function grantTenantPermission(array $fixture, string $permission): void
    {
        $this->seed(PermissionSeeder::class);
        $role = TenantRole::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'name' => 'Venue authorization role '.Str::random(6),
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
