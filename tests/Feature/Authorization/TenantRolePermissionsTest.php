<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\Permission;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Database\Seeders\DemoAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

final class TenantRolePermissionsTest extends TestCase
{
    use BuildsTenantFixtures;
    use RefreshDatabase;

    public function test_replace_permissions_updates_custom_role(): void
    {
        ['user' => $user, 'tenant' => $tenant] = $this->createTenantMember();

        $role = TenantRole::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Custom Role',
            'name_en' => 'Custom Role',
            'name_ar' => 'دور مخصص',
            'is_system' => false,
            'created_by_user_id' => $user->id,
        ]);

        Permission::query()->firstOrCreate(
            ['key' => 'event.view'],
            ['module' => 'events', 'description' => 'View events', 'scope' => 'tenant', 'risk_level' => 'standard'],
        );

        $this->grantTenantPermissions($user, $tenant, ['role.manage']);

        Sanctum::actingAs($user, ['*']);

        $response = $this->putJson("/api/v1/tenant/roles/{$role->id}/permissions", [
            'permissions' => ['event.view'],
        ], $this->tenantHeaders($tenant));

        $response->assertOk()->assertJsonPath('data.permissions', ['event.view']);
    }

    public function test_demo_role_permissions_endpoint_resolves_route_id(): void
    {
        $this->seed();

        $user = User::query()->where('email', DemoAccounts::TENANT_EMAIL)->firstOrFail();
        $tenant = Tenant::query()->where('slug', DemoAccounts::TENANT_SLUG)->firstOrFail();
        $role = TenantRole::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_system', false)
            ->where('created_by_user_id', $user->id)
            ->first();

        if (! $role instanceof TenantRole) {
            $role = TenantRole::query()->create([
                'tenant_id' => $tenant->id,
                'name' => 'Demo Custom',
                'name_en' => 'Demo Custom',
                'name_ar' => 'تجريبي',
                'is_system' => false,
                'created_by_user_id' => $user->id,
            ]);
        }

        Sanctum::actingAs($user, ['*']);

        $response = $this->putJson("/api/v1/tenant/roles/{$role->id}/permissions", [
            'permissions' => ['event.view', 'event.manage'],
        ], $this->tenantHeaders($tenant));

        $response->assertOk();
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function grantTenantPermissions(User $user, Tenant $tenant, array $permissionKeys): void
    {
        $adminRole = TenantRole::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Tenant Administrator',
            'name_en' => 'Tenant Administrator',
            'name_ar' => 'مدير المستأجر',
            'is_system' => true,
            'created_by_user_id' => $user->id,
        ]);

        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id');

        foreach ($permissionIds as $permissionId) {
            \DB::table('tenant_role_permissions')->insert([
                'tenant_id' => $tenant->id,
                'tenant_role_id' => $adminRole->id,
                'permission_id' => $permissionId,
                'granted_by_user_id' => $user->id,
                'created_at' => now(),
            ]);
        }

        $membership = \App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        \DB::table('tenant_role_assignments')->insert([
            'tenant_id' => $tenant->id,
            'tenant_membership_id' => $membership->id,
            'tenant_role_id' => $adminRole->id,
            'granted_by_user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
