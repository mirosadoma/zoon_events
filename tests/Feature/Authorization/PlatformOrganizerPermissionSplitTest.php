<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\Authorization\Application\EnsurePermissionsExist;
use App\Modules\Authorization\Infrastructure\Persistence\Models\Permission;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRoleAssignment;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('rbac')]
final class PlatformOrganizerPermissionSplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_permissions_exist_upserts_missing_platform_keys(): void
    {
        $this->seed(PermissionSeeder::class);

        Permission::query()->where('key', 'platform.configuration.view')->delete();
        self::assertFalse(Permission::query()->where('key', 'platform.configuration.view')->exists());

        $ids = app(EnsurePermissionsExist::class)->forScope('platform', ['platform.configuration.view']);

        self::assertCount(1, $ids);
        self::assertTrue(
            Permission::query()
                ->where('key', 'platform.configuration.view')
                ->where('scope', 'platform')
                ->exists()
        );
    }

    public function test_platform_role_store_creates_missing_catalog_permission_rows(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = $this->platformAdmin(['platform.role.manage', 'platform.role.view', 'platform.tenant.view']);

        Permission::query()->where('key', 'platform.configuration.view')->delete();

        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson('/api/v1/platform/roles', [
            'name' => 'Config Reviewers',
            'description' => 'Can view configuration',
            'permissions' => ['platform.configuration.view'],
        ], ['Idempotency-Key' => (string) Str::uuid()]);

        $response->assertCreated()
            ->assertJsonPath('data.permissions', ['platform.configuration.view']);

        self::assertTrue(Permission::query()->where('key', 'platform.configuration.view')->where('scope', 'platform')->exists());
    }

    public function test_tenant_replace_permissions_creates_missing_catalog_permission_rows(): void
    {
        $this->seed(PermissionSeeder::class);
        ['user' => $user, 'tenant' => $tenant] = $this->createOrganizer();

        $role = TenantRole::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Custom Role',
            'name_en' => 'Custom Role',
            'name_ar' => 'دور مخصص',
            'is_system' => false,
            'created_by_user_id' => $user->id,
        ]);

        $this->grantTenantPermissions($user, $tenant, ['role.manage']);
        Permission::query()->where('key', 'event.view')->delete();

        Sanctum::actingAs($user, ['*']);

        $response = $this->putJson("/api/v1/tenant/roles/{$role->id}/permissions", [
            'permissions' => ['event.view'],
        ], [
            'X-Tenant-ID' => $tenant->id,
            'Accept' => 'application/json',
            'Idempotency-Key' => (string) Str::uuid(),
        ]);

        $response->assertOk()->assertJsonPath('data.permissions', ['event.view']);
        self::assertTrue(Permission::query()->where('key', 'event.view')->where('scope', 'tenant')->exists());
    }

    public function test_platform_admin_login_redirects_to_platform_console(): void
    {
        $this->seed(PermissionSeeder::class);
        $password = 'Synthetic-Password-123!';
        $admin = $this->platformAdmin(
            ['platform.tenant.view', 'platform.role.view'],
            ['password' => Hash::make($password)],
        );

        $this->post('/en/login', [
            'email' => $admin->email,
            'password' => $password,
        ])->assertRedirect('/en/platform/tenants');
    }

    public function test_organizer_login_still_redirects_to_dashboard(): void
    {
        $password = 'Synthetic-Password-123!';
        $user = User::factory()->create(['password' => Hash::make($password)]);

        $this->post('/en/login', [
            'email' => $user->email,
            'password' => $password,
        ])->assertRedirect('/en/dashboard');
    }

    public function test_platform_session_exposes_platform_console_and_hides_tenant_permissions(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = $this->platformAdmin([
            'platform.tenant.view',
            'platform.role.view',
            'platform.user.view',
        ]);

        $request = Request::create('/en/platform/tenants', 'GET');
        $request->setUserResolver(fn () => $admin);

        $context = app(SessionContextBuilder::class)->build($request);

        self::assertSame('platform', $context['session']['console']);
        self::assertTrue($context['can']['platform.tenant.view']);
        self::assertFalse($context['can']['event.view']);
        self::assertFalse($context['can']['role.view']);
    }

    public function test_organizer_session_exposes_organizer_console_and_hides_platform_permissions(): void
    {
        $this->seed(PermissionSeeder::class);
        ['user' => $user, 'tenant' => $tenant] = $this->createOrganizer();
        $this->grantTenantPermissions($user, $tenant, ['event.view', 'role.view']);

        $request = Request::create('/en/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $context = app(SessionContextBuilder::class)->build($request);

        self::assertSame('organizer', $context['session']['console']);
        self::assertTrue($context['can']['event.view']);
        self::assertFalse($context['can']['platform.tenant.view']);
    }

    public function test_platform_home_path_prefers_first_permitted_route(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = $this->platformAdmin(['platform.user.view']);

        self::assertSame('/platform/users', app(SessionContextBuilder::class)->platformHomePath($admin));
    }

    /**
     * @param  list<string>  $permissions
     * @param  array<string, mixed>  $userAttributes
     */
    private function platformAdmin(array $permissions, array $userAttributes = []): User
    {
        $user = User::factory()->create($userAttributes);

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

    /**
     * @return array{user: User, tenant: Tenant, membership: TenantMembership}
     */
    private function createOrganizer(): array
    {
        $creator = User::factory()->create();
        $user = User::factory()->create();
        $tenant = Tenant::query()->create([
            'name' => 'Organizer Tenant',
            'slug' => 'org-'.Str::lower((string) Str::ulid()),
            'status' => LifecycleStatus::Active->value,
            'is_active' => true,
            'organization_type' => 'organizer',
            'default_locale' => 'en',
            'timezone' => 'Africa/Cairo',
            'data_residency_region' => 'eg',
            'created_by_user_id' => $creator->id,
        ]);
        $membership = TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => LifecycleStatus::Active->value,
            'created_by_user_id' => $creator->id,
        ]);

        return compact('user', 'tenant', 'membership');
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

        foreach (Permission::query()->whereIn('key', $permissionKeys)->pluck('id') as $permissionId) {
            DB::table('tenant_role_permissions')->insert([
                'tenant_id' => $tenant->id,
                'tenant_role_id' => $adminRole->id,
                'permission_id' => $permissionId,
                'granted_by_user_id' => $user->id,
                'created_at' => now(),
            ]);
        }

        $membership = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        DB::table('tenant_role_assignments')->insert([
            'tenant_id' => $tenant->id,
            'tenant_membership_id' => $membership->id,
            'tenant_role_id' => $adminRole->id,
            'granted_by_user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
