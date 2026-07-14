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
class AttendeesPaginationExportTest extends TestCase
{
    use BuildsTenantFixtures;
    use RefreshDatabase;

    public function test_attendees_index_includes_filters_and_pagination_props(): void
    {
        ['user' => $user, 'event' => $event] = $this->operationsFixture();
        $password = 'Synthetic-Password-123!';

        $this->post('/login', ['email' => $user->email, 'password' => $password])->assertRedirect('/dashboard');

        $this->get("/tenant/events/{$event->id}/attendees?status=checked_in&search=Ada&page=1")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('tenant/events/Attendees')
                ->has('attendees')
                ->where('filters.search', 'Ada')
                ->where('filters.status', 'checked_in')
                ->where('pagination.page', 1)
                ->where('pagination.per_page', 25)
                ->has('pagination.total')
                ->has('pagination.last_page'));
    }

    public function test_attendees_export_downloads_xlsx_and_respects_filters(): void
    {
        ['user' => $user, 'event' => $event] = $this->operationsFixture();
        $password = 'Synthetic-Password-123!';

        $this->post('/login', ['email' => $user->email, 'password' => $password])->assertRedirect('/dashboard');

        $response = $this->get("/tenant/events/{$event->id}/attendees/export?status=not_checked_in&search=missing@example.test");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        self::assertStringContainsString(
            'attachment; filename=',
            (string) $response->headers->get('content-disposition'),
        );
        self::assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));

        // Empty filtered export is still a valid XLSX (ZIP) package.
        $content = $response->streamedContent();
        self::assertSame('PK', substr($content, 0, 2));
    }

    public function test_attendees_export_requires_permission(): void
    {
        $memberFixture = $this->createTenantMember();
        $password = 'Synthetic-Password-123!';
        $memberFixture['user']->forceFill(['password' => Hash::make($password)])->save();
        $event = Event::query()->create([
            'tenant_id' => $memberFixture['tenant']->id,
            'slug' => 'no-attendee-export',
            'name_en' => 'No Export Event',
            'name_ar' => 'فعالية بدون تصدير',
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
        $this->get("/tenant/events/{$event->id}/attendees/export")->assertForbidden();
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
            'slug' => 'attendees-export-event',
            'name_en' => 'Attendees Export Event',
            'name_ar' => 'فعالية تصدير الحضور',
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
