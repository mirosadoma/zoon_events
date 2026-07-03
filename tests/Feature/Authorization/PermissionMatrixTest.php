<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

#[Group('rbac')]
class PermissionMatrixTest extends TestCase
{
    use BuildsTenantFixtures;
    use RefreshDatabase;

    public function test_every_seeded_permission_has_explicit_allow_and_deny_evidence(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $fixture = $this->createTenantMember();
        $context = app(TenantContextStore::class)->bind($fixture['tenant'], $fixture['membership'], $fixture['user']);
        $tenantRole = TenantRole::query()->create(['tenant_id' => $fixture['tenant']->id, 'name' => 'Matrix', 'is_system' => false, 'created_by_user_id' => $fixture['user']->id]);
        $tenantPermissions = DB::table('permissions')->where('scope', 'tenant')->get();
        foreach ($tenantPermissions as $permission) {
            DB::table('tenant_role_permissions')->insert(['tenant_id' => $fixture['tenant']->id, 'tenant_role_id' => $tenantRole->id, 'permission_id' => $permission->id, 'granted_by_user_id' => $fixture['user']->id, 'created_at' => now()]);
        }
        DB::table('tenant_role_assignments')->insert(['id' => (string) Str::ulid(), 'tenant_id' => $fixture['tenant']->id, 'tenant_membership_id' => $fixture['membership']->id, 'tenant_role_id' => $tenantRole->id, 'granted_by_user_id' => $fixture['user']->id, 'created_at' => now(), 'updated_at' => now()]);

        $platformRole = PlatformRole::query()->create(['name' => 'Matrix platform', 'is_system' => false, 'created_by_user_id' => $fixture['user']->id]);
        $platformPermissions = DB::table('permissions')->where('scope', 'platform')->get();
        foreach ($platformPermissions as $permission) {
            DB::table('platform_role_permissions')->insert(['platform_role_id' => $platformRole->id, 'permission_id' => $permission->id, 'granted_by_user_id' => $fixture['user']->id, 'created_at' => now()]);
        }
        DB::table('platform_role_assignments')->insert(['id' => (string) Str::ulid(), 'user_id' => $fixture['user']->id, 'platform_role_id' => $platformRole->id, 'granted_by_user_id' => $fixture['user']->id, 'created_at' => now(), 'updated_at' => now()]);

        $denied = User::factory()->create();
        $evaluator = app(PermissionEvaluator::class);
        foreach ($tenantPermissions as $permission) {
            self::assertTrue($evaluator->hasTenantPermission($context, $permission->key), $permission->key);
        }
        foreach ($platformPermissions as $permission) {
            self::assertTrue($evaluator->hasPlatformPermission($fixture['user'], $permission->key), $permission->key);
            self::assertFalse($evaluator->hasPlatformPermission($denied, $permission->key), $permission->key);
        }
    }
}
