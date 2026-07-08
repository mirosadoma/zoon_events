<?php

namespace Tests\Feature\AdminConsole;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

#[Group('admin-dashboard')]
class AcsAuthTest extends TestCase
{
    use BuildsTenantFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_is_redirected_from_acs_routes(): void
    {
        $this->get('/tenant/events/evt_1/acs')->assertRedirect('/login');
        $this->get('/tenant/events/evt_1/acs/zones')->assertRedirect('/login');
    }

    public function test_tenant_administrator_can_view_acs_pages(): void
    {
        ['user' => $user, 'event' => $event] = $this->acsFixture();
        $password = 'Synthetic-Password-123!';

        $this->post('/login', ['email' => $user->email, 'password' => $password])->assertRedirect('/dashboard');

        $this->get("/tenant/events/{$event->id}/acs")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/acs/Index')->has('overview'));

        $this->get("/tenant/events/{$event->id}/acs/zones")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/acs/Zones')->has('zones'));

        $this->get("/tenant/events/{$event->id}/acs/lanes")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/acs/Lanes')->has('lanes'));

        $this->get("/tenant/events/{$event->id}/acs/rules")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/acs/Rules')->has('rules'));

        $this->get("/tenant/events/{$event->id}/acs/access-logs")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/acs/AccessLogs')->has('accessEvents'));

        $this->get("/tenant/events/{$event->id}/acs/gate-health")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/acs/GateHealth')->has('health'));
    }

    public function test_member_without_role_is_forbidden(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $fixture = $this->createTenantMember();
        $password = 'Synthetic-Password-123!';
        $fixture['user']->forceFill(['password' => Hash::make($password)])->save();

        $event = Event::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'slug' => 'no-role-acs-event',
            'name_en' => 'No Role Event',
            'name_ar' => 'فعالية بدون دور',
            'tier' => 'public',
            'status' => 'draft',
            'timezone' => 'Africa/Cairo',
            'start_at' => now()->addMonth(),
            'end_at' => now()->addMonth()->addHours(4),
            'registration_opens_at' => now(),
            'registration_closes_at' => now()->addMonth()->subHour(),
            'capacity' => 100,
            'created_by_user_id' => $fixture['user']->id,
        ]);

        $this->post('/login', ['email' => $fixture['user']->email, 'password' => $password]);
        $this->get("/tenant/events/{$event->id}/acs")->assertForbidden();
        $this->get("/tenant/events/{$event->id}/acs/zones")->assertForbidden();
    }

    /**
     * @return array{user:User,tenant:Tenant,event:Event}
     */
    private function acsFixture(): array
    {
        $this->seed(PermissionCatalogSeeder::class);
        $fixture = $this->createTenantMember();
        $password = 'Synthetic-Password-123!';
        $fixture['user']->forceFill(['password' => Hash::make($password)])->save();

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

        $event = Event::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'slug' => 'acs-event',
            'name_en' => 'ACS Event',
            'name_ar' => 'فعالية ACS',
            'tier' => 'public',
            'status' => 'published',
            'timezone' => 'Africa/Cairo',
            'start_at' => now()->addMonth(),
            'end_at' => now()->addMonth()->addHours(4),
            'registration_opens_at' => now(),
            'registration_closes_at' => now()->addMonth()->subHour(),
            'capacity' => 100,
            'created_by_user_id' => $fixture['user']->id,
        ]);

        return [
            'user' => $fixture['user'],
            'tenant' => $fixture['tenant'],
            'event' => $event,
        ];
    }
}
