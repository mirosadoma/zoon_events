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
class EventsAuthTest extends TestCase
{
    use BuildsTenantFixtures;
    use RefreshDatabase;

    public function test_guest_is_redirected_from_event_routes(): void
    {
        $this->get('/tenant/events')->assertRedirect('/login');
    }

    public function test_tenant_administrator_can_view_event_pages(): void
    {
        ['user' => $user, 'tenant' => $tenant, 'event' => $event] = $this->tenantEventFixture();
        $password = 'Synthetic-Password-123!';

        $this->post('/login', ['email' => $user->email, 'password' => $password])->assertRedirect('/dashboard');

        $this->get('/tenant/events')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/events/List')->has('events'));

        $this->get("/tenant/events/{$event->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/events/Detail')->where('event.id', $event->id));

        $this->get("/tenant/events/{$event->id}/ticket-types")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/events/Ticketing'));
    }

    public function test_member_without_role_is_forbidden(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $fixture = $this->createTenantMember();
        $password = 'Synthetic-Password-123!';
        $fixture['user']->forceFill(['password' => Hash::make($password)])->save();

        $this->post('/login', ['email' => $fixture['user']->email, 'password' => $password]);
        $this->get('/tenant/events')->assertForbidden();
    }

    /**
     * @return array{user:User,tenant:Tenant,event:Event}
     */
    private function tenantEventFixture(): array
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
            'slug' => 'dashboard-event',
            'name_en' => 'Dashboard Event',
            'name_ar' => 'فعالية لوحة التحكم',
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

        return [
            'user' => $fixture['user'],
            'tenant' => $fixture['tenant'],
            'event' => $event,
        ];
    }
}
