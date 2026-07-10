<?php

namespace Tests\Feature\AdminConsole;

use App\Models\User;
use App\Modules\AdminConsole\Application\MembershipVisibility;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

#[Group('admin-dashboard')]
final class MembershipVisibilityTest extends TestCase
{
    use BuildsTenantFixtures;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(PermissionCatalogSeeder::class);
    }

    public function test_admin_users_page_only_lists_memberships_the_actor_invited_without_system_roles(): void
    {
        $fixture = $this->tenantAdminFixture();
        $invited = User::factory()->create(['email' => 'invited.staff@example.test']);
        TenantMembership::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'user_id' => $invited->id,
            'status' => 'active',
            'created_by_user_id' => $fixture['user']->id,
        ]);

        $seededPeer = User::factory()->create(['email' => 'seeded.supervisor@example.test']);
        TenantMembership::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'user_id' => $seededPeer->id,
            'status' => 'active',
            'created_by_user_id' => User::factory()->create()->id,
        ]);

        $this->post('/login', [
            'email' => $fixture['user']->email,
            'password' => 'Synthetic-Password-123!',
        ])->assertRedirect('/dashboard');

        $this->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Users')
                ->has('users', 1)
                ->where('users.0.email', 'invited.staff@example.test')
            );
    }

    public function test_assignable_scope_hides_invited_users_with_active_system_roles(): void
    {
        $fixture = $this->tenantAdminFixture();
        $invited = User::factory()->create(['email' => 'already.supervisor@example.test']);
        $membership = TenantMembership::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'user_id' => $invited->id,
            'status' => 'active',
            'created_by_user_id' => $fixture['user']->id,
        ]);

        $systemRole = TenantRole::query()->withoutGlobalScopes()->create([
            'tenant_id' => $fixture['tenant']->id,
            'name' => 'On-Site Staff',
            'description' => 'Seeded system role',
            'is_system' => true,
            'created_by_user_id' => $fixture['user']->id,
        ]);

        DB::table('tenant_role_assignments')->insert([
            'tenant_id' => $fixture['tenant']->id,
            'tenant_membership_id' => $membership->id,
            'tenant_role_id' => $systemRole->id,
            'granted_by_user_id' => $fixture['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $context = new TenantContext($fixture['tenant'], $fixture['membership'], $fixture['user']);
        $visible = app(MembershipVisibility::class)->scopeAssignableMemberships(
            TenantMembership::query()->with('user'),
            $context,
            $fixture['user'],
        )->get();

        self::assertCount(0, $visible);
    }

    /**
     * @return array{user:User,tenant:Tenant,membership:TenantMembership}
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
