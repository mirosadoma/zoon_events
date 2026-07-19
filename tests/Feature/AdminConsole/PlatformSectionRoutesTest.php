<?php

namespace Tests\Feature\AdminConsole;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRoleAssignment;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('admin-dashboard')]
final class PlatformSectionRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_section_routes_render_for_super_administrator(): void
    {
        $user = $this->superAdministrator();

        $this->actingAs($user)->get('/en/platform/tenants')->assertOk();
        $this->actingAs($user)->get('/en/platform/users')->assertOk();
        $this->actingAs($user)->get('/en/platform/roles')->assertOk();
        $this->actingAs($user)->get('/en/platform/audit')->assertOk();
        $this->actingAs($user)->get('/en/platform/health')->assertOk();
        $this->actingAs($user)->get('/en/platform/feature-flags')->assertOk();
        $this->actingAs($user)->get('/en/platform/configuration')->assertOk();
        $this->actingAs($user)->get('/en/platform/geography')->assertOk();
        $this->actingAs($user)->get('/en/platform/site-settings')->assertOk();
        $this->actingAs($user)->get('/en/platform/organizer-requests')->assertOk();
    }

    private function superAdministrator(): User
    {
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create([
            'email' => 'super.admin@admin.com',
            'password' => Hash::make('admin1234'),
        ]);

        $role = PlatformRole::query()->create([
            'name' => 'Super Administrator',
            'description' => 'Test super admin',
            'is_system' => true,
            'created_by_user_id' => $user->id,
        ]);

        foreach (DB::table('permissions')->where('scope', 'platform')->pluck('id') as $permissionId) {
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
