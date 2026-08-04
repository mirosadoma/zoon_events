<?php

namespace Tests\Feature\AdminConsole;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('admin-dashboard')]
final class EventReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_is_redirected_from_event_report_export(): void
    {
        $this->get('/en/tenant/events/evt_1/reports/export')->assertRedirect('/en/login');
    }

    public function test_tenant_administrator_can_export_event_report_csv(): void
    {
        ['user' => $user, 'event' => $event] = $this->adminFixture();
        $password = 'Synthetic-Password-123!';

        $this->post('/en/login', ['email' => $user->email, 'password' => $password])->assertRedirect('/en/dashboard');

        $response = $this->get("/en/tenant/events/{$event->id}/reports/export");

        $response->assertOk();
        self::assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $content = $response->streamedContent();
        self::assertStringStartsWith("\xEF\xBB\xBF", $content);
        self::assertStringContainsString('Summary', $content);
        self::assertStringContainsString('Funnel', $content);
        self::assertStringContainsString('Venues', $content);
    }

    /**
     * @return array{user: User, tenant: Tenant, event: Event}
     */
    private function adminFixture(): array
    {
        $this->seed(PermissionCatalogSeeder::class);

        $creator = User::factory()->create();
        $user = User::factory()->create([
            'password' => Hash::make('Synthetic-Password-123!'),
        ]);
        $tenant = Tenant::factory()->create(['created_by_user_id' => $creator->id]);
        $membership = TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => 'active',
            'created_by_user_id' => $creator->id,
        ]);

        $role = TenantRole::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Tenant Administrator',
            'description' => 'Test tenant admin',
            'is_system' => true,
            'created_by_user_id' => $user->id,
        ]);

        foreach (DB::table('permissions')->where('scope', 'tenant')->pluck('id') as $permissionId) {
            DB::table('tenant_role_permissions')->insert([
                'tenant_id' => $tenant->id,
                'tenant_role_id' => $role->id,
                'permission_id' => $permissionId,
                'granted_by_user_id' => $user->id,
                'created_at' => now(),
            ]);
        }

        DB::table('tenant_role_assignments')->insert([
            'tenant_id' => $tenant->id,
            'tenant_membership_id' => $membership->id,
            'tenant_role_id' => $role->id,
            'granted_by_user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $event = Event::query()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'report-export-event',
            'name_en' => 'Report Export Event',
            'name_ar' => 'فعالية تصدير التقرير',
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

        return [
            'user' => $user,
            'tenant' => $tenant,
            'event' => $event,
        ];
    }
}
