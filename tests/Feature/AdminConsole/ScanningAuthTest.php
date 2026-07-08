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
class ScanningAuthTest extends TestCase
{
    use BuildsTenantFixtures;
    use RefreshDatabase;

    public function test_guest_is_redirected_from_scanning_routes(): void
    {
        $this->get('/tenant/events/evt_1/check-in-dashboard')->assertRedirect('/login');
    }

    public function test_tenant_administrator_can_view_scanning_pages(): void
    {
        ['user' => $user, 'event' => $event] = $this->scanningFixture();
        $password = 'Synthetic-Password-123!';

        $this->post('/login', ['email' => $user->email, 'password' => $password])->assertRedirect('/dashboard');

        $this->get("/tenant/events/{$event->id}/check-in-dashboard")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/checkin/Dashboard')->where('event.id', $event->id));

        $this->get("/tenant/events/{$event->id}/scanner")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/checkin/Scanner'));

        $this->get("/tenant/events/{$event->id}/wallet-passes")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/checkin/WalletPasses')->has('walletPasses'));

        $this->get("/tenant/events/{$event->id}/scan-events")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/checkin/ScanEvents')->has('scanEvents'));
    }

    public function test_cross_tenant_event_is_not_found(): void
    {
        ['user' => $user] = $this->scanningFixture();
        $other = $this->createTenantMember();
        $password = 'Synthetic-Password-123!';
        $user->forceFill(['password' => Hash::make($password)])->save();

        $foreignEvent = Event::query()->create([
            'tenant_id' => $other['tenant']->id,
            'slug' => 'foreign-scan-event',
            'name_en' => 'Foreign Event',
            'name_ar' => 'فعالية أجنبية',
            'tier' => 'public',
            'status' => 'draft',
            'timezone' => 'Africa/Cairo',
            'start_at' => now()->addMonth(),
            'end_at' => now()->addMonth()->addHours(4),
            'registration_opens_at' => now(),
            'registration_closes_at' => now()->addMonth()->subHour(),
            'capacity' => 100,
            'created_by_user_id' => $other['user']->id,
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => $password]);
        $this->get("/tenant/events/{$foreignEvent->id}/scanner")->assertNotFound();
    }

    public function test_member_without_role_is_forbidden(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $fixture = $this->createTenantMember();
        $password = 'Synthetic-Password-123!';
        $fixture['user']->forceFill(['password' => Hash::make($password)])->save();

        $event = Event::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'slug' => 'no-role-scan-event',
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
        $this->get("/tenant/events/{$event->id}/check-in-dashboard")->assertForbidden();
    }

    /**
     * @return array{user:User,tenant:Tenant,event:Event}
     */
    private function scanningFixture(): array
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
            'slug' => 'scanning-event',
            'name_en' => 'Scanning Event',
            'name_ar' => 'فعالية المسح',
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
