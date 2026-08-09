<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRoleAssignment;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

#[Group('tenancy')]
final class PlatformTenantManagementTest extends TestCase
{
    use BuildsTenantFixtures;
    use RefreshDatabase;

    public function test_platform_admin_can_disable_tenant_and_member_cannot_login(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = $this->platformAdmin(['platform.tenant.manage', 'platform.tenant.view']);
        $fixture = $this->createTenantMember(['password' => 'Synthetic-Password-123!']);

        Sanctum::actingAs($admin, ['*']);

        $this->patchJson('/api/v1/platform/tenants/'.$fixture['tenant']->id, [
            'is_active' => false,
            'reason' => 'Disabled for test',
        ], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.status', 'suspended');

        $this->postJson('/api/v1/auth/token', [
            'email' => $fixture['user']->email,
            'password' => 'Synthetic-Password-123!',
            'device_name' => 'test',
        ])->assertForbidden()
            ->assertJsonPath('code', 'tenant_inactive');
    }

    public function test_cannot_delete_tenant_with_published_or_upcoming_events(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = $this->platformAdmin(['platform.tenant.manage', 'platform.tenant.view']);
        $fixture = $this->createTenantMember();

        Event::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'name_en' => 'Live Event',
            'name_ar' => 'فعالية',
            'slug' => 'live-event-'.Str::lower((string) Str::ulid()),
            'tier' => 'public',
            'event_type' => 'conference',
            'registration_mode' => 'free_registration',
            'status' => 'published',
            'timezone' => 'Africa/Cairo',
            'start_at' => now()->addDays(3),
            'end_at' => now()->addDays(4),
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDays(3),
            'created_by_user_id' => $fixture['user']->id,
            'published_by_user_id' => $fixture['user']->id,
            'published_at' => now(),
        ]);

        Sanctum::actingAs($admin, ['*']);

        $this->deleteJson('/api/v1/platform/tenants/'.$fixture['tenant']->id, [], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertStatus(409)
            ->assertJsonPath('code', 'tenant_has_active_events');

        self::assertTrue(Tenant::query()->whereKey($fixture['tenant']->id)->exists());
    }

    public function test_platform_admin_can_update_role_permissions(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = $this->platformAdmin(['platform.role.manage', 'platform.role.view']);

        $role = PlatformRole::query()->create([
            'name' => 'Custom Platform Role',
            'description' => 'Editable',
            'is_system' => false,
            'created_by_user_id' => $admin->id,
        ]);

        $permissionId = DB::table('permissions')->where('key', 'platform.tenant.view')->value('id');
        DB::table('platform_role_permissions')->insert([
            'platform_role_id' => $role->id,
            'permission_id' => $permissionId,
            'granted_by_user_id' => $admin->id,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->patchJson('/api/v1/platform/roles/'.$role->id, [
            'name' => 'Custom Platform Role Updated',
            'permissions' => ['platform.tenant.view', 'platform.tenant.manage'],
        ], ['Idempotency-Key' => (string) Str::uuid()]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Custom Platform Role Updated');

        self::assertEqualsCanonicalizing(
            ['platform.tenant.view', 'platform.tenant.manage'],
            $response->json('data.permissions')
        );
    }

    public function test_platform_admin_can_create_and_update_organizer_phone(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = $this->platformAdmin(['platform.tenant.manage', 'platform.tenant.view']);

        Sanctum::actingAs($admin, ['*']);

        $create = $this->postJson('/api/v1/platform/tenants', [
            'name' => 'Organizer Admin',
            'email' => 'organizer.phone@example.test',
            'organization_name' => 'Phone Org',
            'phone' => '+966500000001',
            'password' => 'Synthetic-Password-123!',
            'password_confirmation' => 'Synthetic-Password-123!',
        ], ['Idempotency-Key' => (string) Str::uuid()]);

        $create->assertCreated()
            ->assertJsonPath('data.admin.phone', '+966500000001')
            ->assertJsonPath('data.admin.email', 'organizer.phone@example.test');

        $tenantId = $create->json('data.id');
        self::assertNotNull($tenantId);

        $this->patchJson('/api/v1/platform/tenants/'.$tenantId, [
            'phone' => '+966511111111',
            'reason' => 'Update organizer phone',
        ], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertOk()
            ->assertJsonPath('data.admin.phone', '+966511111111');

        $this->assertDatabaseHas('users', [
            'email' => 'organizer.phone@example.test',
            'phone' => '+966511111111',
        ]);
    }

    public function test_platform_tenant_admin_prefers_non_platform_member(): void
    {
        $this->seed(PermissionSeeder::class);
        $platformAdmin = $this->platformAdmin(['platform.tenant.view', 'platform.tenant.manage']);
        $fixture = $this->createTenantMember([
            'email' => 'demo.organizer@example.test',
            'name' => 'Organizer Admin',
        ]);

        // Mimic FoundationSeeder: platform staff also has a tenant membership (often created first).
        TenantMembership::factory()->create([
            'tenant_id' => $fixture['tenant']->id,
            'user_id' => $platformAdmin->id,
            'status' => 'active',
            'created_by_user_id' => $platformAdmin->id,
        ]);

        Sanctum::actingAs($platformAdmin, ['*']);

        $this->getJson('/api/v1/platform/tenants/'.$fixture['tenant']->id)
            ->assertOk()
            ->assertJsonPath('data.admin.email', 'demo.organizer@example.test')
            ->assertJsonPath('data.admin.name', 'Organizer Admin');
    }

    public function test_platform_tenants_page_excludes_venue_owners_and_platform_admin_email(): void
    {
        $this->seed(PermissionSeeder::class);
        $platformAdmin = $this->platformAdmin(['platform.tenant.view']);

        $organizer = $this->createTenantMember(
            ['email' => 'demo@zonetec.test', 'name' => 'Organizer Admin'],
            ['organization_type' => 'organizer', 'name' => 'Zonetec Events'],
        );
        $this->createTenantMember(
            ['email' => 'venue@example.test'],
            ['organization_type' => 'venue_owner', 'name' => 'Venue Only Co'],
        );

        TenantMembership::factory()->create([
            'tenant_id' => $organizer['tenant']->id,
            'user_id' => $platformAdmin->id,
            'status' => 'active',
            'created_by_user_id' => $platformAdmin->id,
        ]);

        $this->actingAs($platformAdmin)
            ->get('/en/platform/tenants')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('platform/Section')
                ->where('section', 'tenants')
                ->has('rows', 1)
                ->where('rows.0.name', 'Zonetec Events')
                ->where('rows.0.admin.email', 'demo@zonetec.test')
                ->has('timezones')
            );
    }

    /** @param list<string> $permissions */
    private function platformAdmin(array $permissions): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('admin1234'),
        ]);

        $role = PlatformRole::query()->create([
            'name' => 'Platform Admin '.Str::lower((string) Str::ulid()),
            'description' => 'Test platform admin',
            'is_system' => false,
            'created_by_user_id' => $user->id,
        ]);

        foreach (DB::table('permissions')->whereIn('key', $permissions)->pluck('id') as $permissionId) {
            DB::table('platform_role_permissions')->insert([
                'platform_role_id' => $role->id,
                'permission_id' => $permissionId,
                'granted_by_user_id' => $user->id,
                'created_at' => now(),
            ]);
        }

        PlatformRoleAssignment::query()->create([
            'user_id' => $user->id,
            'platform_role_id' => $role->id,
            'granted_by_user_id' => $user->id,
        ]);

        return $user;
    }
}
