<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BuildsTenantFixtures;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

final class TenantRoleAssignmentTest extends Phase2MySqlTestCase
{
    use BuildsTenantFixtures;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_tenant_admin_can_assign_custom_role_to_invited_membership(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $fixture = $this->createTenantMember();
        $admin = $fixture['user'];
        $tenant = $fixture['tenant'];

        $this->grantTenantPermissions($tenant, $fixture['membership'], ['role.assign', 'membership.manage']);

        $invitee = User::factory()->create(['created_by_user_id' => $admin->id]);
        $membership = TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $invitee->id,
            'status' => 'active',
            'created_by_user_id' => $admin->id,
        ]);

        $role = TenantRole::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Custom role',
            'description' => 'Test custom role',
            'is_system' => false,
            'created_by_user_id' => $admin->id,
        ]);

        DB::table('tenant_role_permissions')->insert([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $role->id,
            'permission_id' => DB::table('permissions')->where('key', 'attendee.view')->value('id'),
            'granted_by_user_id' => $admin->id,
            'created_at' => now(),
        ]);

        $this->actingAsTenantMember($admin, $tenant);

        $this->postJson('/api/v1/tenant/role-assignments', [
            'membership_id' => (string) $membership->id,
            'role_id' => (string) $role->id,
        ], [
            'X-Tenant-ID' => (string) $tenant->id,
            'Idempotency-Key' => (string) Str::ulid(),
        ])
            ->assertCreated()
            ->assertJsonMissing(['code' => 'service_unavailable']);
    }
}
