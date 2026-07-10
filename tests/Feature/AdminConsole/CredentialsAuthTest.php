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
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

#[Group('admin-dashboard')]
class CredentialsAuthTest extends TestCase
{
    use BuildsTenantFixtures;
    use RefreshDatabase;

    public function test_operations_routes_require_auth_tenant_scope_and_permissions(): void
    {
        $this->get('/tenant/events/evt_1/orders')->assertRedirect('/login');

        ['user' => $user, 'event' => $event] = $this->operationsFixture();
        $password = 'Synthetic-Password-123!';

        $this->post('/login', ['email' => $user->email, 'password' => $password])->assertRedirect('/dashboard');

        $this->get("/tenant/events/{$event->id}/orders")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/events/Orders')->has('orders'));

        $this->get("/tenant/events/{$event->id}/attendees")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/events/Attendees')->has('attendees'));

        $this->get("/tenant/events/{$event->id}/credentials")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('tenant/events/Credentials')->has('credentials'));

        $missingId = '01JZZZZZZZZZZZZZZZZZZZZZZZ';
        $this->get("/tenant/events/{$event->id}/orders/{$missingId}")->assertNotFound();
        $this->get("/tenant/events/{$event->id}/attendees/{$missingId}")->assertNotFound();
        $this->get("/tenant/events/{$event->id}/credentials/{$missingId}")->assertNotFound();

        $other = $this->createTenantMember();
        $foreignEvent = Event::query()->create([
            'tenant_id' => $other['tenant']->id,
            'slug' => 'foreign-event',
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
        $this->get("/tenant/events/{$foreignEvent->id}/orders")->assertNotFound();

        $this->post('/logout');

        $memberFixture = $this->createTenantMember();
        $memberFixture['user']->forceFill(['password' => Hash::make($password)])->save();
        $memberEvent = Event::query()->create([
            'tenant_id' => $memberFixture['tenant']->id,
            'slug' => 'no-role-event',
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
            'created_by_user_id' => $memberFixture['user']->id,
        ]);

        $this->post('/login', ['email' => $memberFixture['user']->email, 'password' => $password]);
        $this->get("/tenant/events/{$memberEvent->id}/credentials")->assertForbidden();
    }

    /**
     * @return array{user:User,tenant:Tenant,event:Event}
     */
    private function operationsFixture(): array
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
            'id' => (string) Str::ulid(),
            'tenant_id' => $fixture['tenant']->id,
            'tenant_membership_id' => $fixture['membership']->id,
            'tenant_role_id' => $role->id,
            'granted_by_user_id' => $fixture['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $event = Event::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'slug' => 'operations-event',
            'name_en' => 'Operations Event',
            'name_ar' => 'فعالية العمليات',
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
