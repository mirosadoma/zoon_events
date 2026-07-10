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
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsTenantFixtures;
use Tests\TestCase;

#[Group('admin-dashboard')]
class CrossTenantPropsTest extends TestCase
{
    use BuildsTenantFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_pages_do_not_include_other_tenant_props(): void
    {
        ['user' => $user, 'tenant' => $tenant] = $this->tenantAdminFixture();
        $other = $this->createTenantMember([
            'email' => 'other-tenant-user@example.test',
            'name' => 'Other Tenant User',
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'Synthetic-Password-123!']);

        $this->get('/admin/users')
            ->assertOk()
            ->assertSee($user->email)
            ->assertDontSee($other['user']->email);

        $this->get('/admin/tenant-settings')
            ->assertOk()
            ->assertSee($tenant->name)
            ->assertDontSee($other['tenant']->name);
    }

    public function test_event_pages_reject_other_tenant_event_ids(): void
    {
        ['user' => $user] = $this->tenantAdminFixture();
        $other = $this->createTenantMember();
        $otherEvent = $this->eventFor($other['tenant'], $other['user'], 'other-tenant-event');

        $this->post('/login', ['email' => $user->email, 'password' => 'Synthetic-Password-123!']);

        foreach ([
            "/tenant/events/{$otherEvent->id}",
            "/tenant/events/{$otherEvent->id}/orders",
            "/tenant/events/{$otherEvent->id}/attendees",
            "/tenant/events/{$otherEvent->id}/credentials",
            "/tenant/events/{$otherEvent->id}/wallet-passes",
            "/tenant/events/{$otherEvent->id}/scanner",
            "/tenant/events/{$otherEvent->id}/check-in-dashboard",
            "/tenant/events/{$otherEvent->id}/scan-events",
            "/tenant/events/{$otherEvent->id}/kiosks",
            "/tenant/events/{$otherEvent->id}/badge-print-jobs",
            "/tenant/events/{$otherEvent->id}/manual-desk",
            "/tenant/events/{$otherEvent->id}/acs",
            "/tenant/events/{$otherEvent->id}/reports",
            "/tenant/events/{$otherEvent->id}/identity",
            "/tenant/events/{$otherEvent->id}/identity/review",
        ] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    /**
     * @return array{user:User,tenant:Tenant,event:Event}
     */
    private function tenantAdminFixture(): array
    {
        $this->seed(PermissionCatalogSeeder::class);
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

        return [
            'user' => $fixture['user'],
            'tenant' => $fixture['tenant'],
            'event' => $this->eventFor($fixture['tenant'], $fixture['user'], 'current-tenant-event'),
        ];
    }

    private function eventFor(Tenant $tenant, User $user, string $slug): Event
    {
        return Event::query()->create([
            'tenant_id' => $tenant->id,
            'slug' => $slug,
            'name_en' => str($slug)->headline()->toString(),
            'name_ar' => 'فعالية اختبار',
            'tier' => 'public',
            'status' => 'published',
            'timezone' => 'Africa/Cairo',
            'start_at' => now()->addMonth(),
            'end_at' => now()->addMonth()->addHours(4),
            'registration_opens_at' => now(),
            'registration_closes_at' => now()->addMonth()->subHour(),
            'capacity' => 100,
            'created_by_user_id' => $user->id,
        ]);
    }
}
