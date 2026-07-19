<?php

namespace Tests\Feature\AdminConsole;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

#[Group('admin-dashboard')]
class KioskBadgeAuthTest extends TestCase
{
    use BuildsTenantFixtures;
    use RefreshDatabase;

    public function test_guest_is_redirected_from_kiosk_badge_routes(): void
    {
        $this->get('/tenant/events/evt_1/kiosks')->assertRedirect('/login');
        $this->get('/tenant/events/evt_1/badge-print-jobs')->assertRedirect('/login');
        $this->get('/tenant/events/evt_1/manual-desk')->assertRedirect('/login');
    }

    public function test_kiosk_mode_is_public_without_auth(): void
    {
        ['event' => $event, 'tenant' => $tenant] = $this->kioskFixture();

        $kiosk = Kiosk::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'device_name' => 'Lobby kiosk',
            'device_code' => 'PUBLICK1',
            'status' => 'registered',
            'printer_status' => 'unknown',
            'confirmation_required' => false,
        ]);

        $this->get("/kiosk/{$kiosk->device_code}")
            ->assertRedirect("/kiosk/{$kiosk->device_code}/unlock");

        $this->get("/kiosk/{$kiosk->device_code}/unlock")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('kiosk/Mode')
                ->where('deviceCode', 'PUBLICK1')
                ->where('step', 'unlock'));

        $this->get("/en/kiosk/{$kiosk->device_code}")
            ->assertRedirect("/en/kiosk/{$kiosk->device_code}/unlock");

        $this->get("/en/kiosk/{$kiosk->device_code}/scan")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('kiosk/Mode')
                ->where('deviceCode', 'PUBLICK1')
                ->where('step', 'scan'));
    }

    public function test_tenant_administrator_can_view_kiosk_badge_and_desk_pages(): void
    {
        ['user' => $user, 'event' => $event, 'tenant' => $tenant] = $this->kioskFixture();
        $password = 'Synthetic-Password-123!';

        $kiosk = Kiosk::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'device_name' => 'Lobby kiosk',
            'device_code' => 'LOBBY001',
            'status' => 'online',
            'printer_status' => 'ready',
            'confirmation_required' => false,
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => $password])->assertRedirect('/dashboard');

        $this->get("/tenant/events/{$event->id}/kiosks")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/kiosk/Index')->has('kiosks'));

        $this->get("/tenant/events/{$event->id}/kiosks/{$kiosk->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/kiosk/Detail')->where('kiosk.id', $kiosk->id));

        $this->get("/tenant/events/{$event->id}/badge-templates")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/badge-templates/Designer'));

        $this->get("/tenant/events/{$event->id}/badge-print-jobs")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/badges/PrintJobs'));

        $this->get("/tenant/events/{$event->id}/manual-desk")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/manual-desk/Desk'));

        $this->get("/tenant/events/{$event->id}/manual-desk/walk-up")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/manual-desk/WalkUp'));
    }

    public function test_member_without_role_is_forbidden(): void
    {
        $this->seed(PermissionSeeder::class);
        $fixture = $this->createTenantMember();
        $password = 'Synthetic-Password-123!';
        $fixture['user']->forceFill(['password' => Hash::make($password)])->save();

        $event = Event::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'slug' => 'no-role-kiosk-event',
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
        $this->get("/tenant/events/{$event->id}/kiosks")->assertForbidden();
        $this->get("/tenant/events/{$event->id}/manual-desk")->assertForbidden();
    }

    /**
     * @return array{user:User,tenant:Tenant,event:Event}
     */
    private function kioskFixture(): array
    {
        $this->seed(PermissionSeeder::class);
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
            'slug' => 'kiosk-badge-event',
            'name_en' => 'Kiosk Badge Event',
            'name_ar' => 'فعالية الكشك',
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
