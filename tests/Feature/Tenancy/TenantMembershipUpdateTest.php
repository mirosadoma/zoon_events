<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

#[Group('tenant-isolation')]
final class TenantMembershipUpdateTest extends TestCase
{
    use BuildsTenantFixtures;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogSeeder::class);
    }

    public function test_tenant_admin_can_suspend_an_invited_membership(): void
    {
        $fixture = $this->tenantAdminFixture();
        $invited = User::factory()->create(['email' => 'suspend.me@example.test']);
        $membership = TenantMembership::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'user_id' => $invited->id,
            'status' => 'active',
            'created_by_user_id' => $fixture['user']->id,
        ]);

        $this->actingAsTenantMember($fixture['user'], $fixture['tenant']);

        $this->patchJson(
            '/api/v1/tenant/memberships/'.$membership->id,
            ['status' => 'suspended', 'reason' => 'Policy violation'],
            [
                'X-Tenant-ID' => (string) $fixture['tenant']->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->assertSame('suspended', $membership->fresh()?->status->value);
    }

    /**
     * @return array{user: User, tenant: Tenant, membership: TenantMembership}
     */
    private function tenantAdminFixture(): array
    {
        $fixture = $this->createTenantMember();
        $fixture['user']->forceFill(['password' => Hash::make('Synthetic-Password-123!')])->save();

        $role = TenantRole::query()->withoutGlobalScopes()->create([
            'tenant_id' => $fixture['tenant']->id,
            'name' => 'Tenant Administrator',
            'description' => 'Test tenant admin',
            'is_system' => true,
            'created_by_user_id' => $fixture['user']->id,
        ]);

        foreach (DB::table('permissions')->where('scope', 'tenant')->pluck('id') as $permissionId) {
            DB::table('tenant_role_permissions')->insert([
                'tenant_id' => $fixture['tenant']->id,
                'tenant_role_id' => $role->id,
                'permission_id' => $permissionId,
                'granted_by_user_id' => $fixture['user']->id,
                'created_at' => now(),
            ]);
        }

        DB::table('tenant_role_assignments')->insert([
            'tenant_id' => $fixture['tenant']->id,
            'tenant_membership_id' => $fixture['membership']->id,
            'tenant_role_id' => $role->id,
            'granted_by_user_id' => $fixture['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $fixture;
    }
}
