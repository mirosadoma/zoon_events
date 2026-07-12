<?php

namespace Tests\Feature\Events;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Database\Seeders\PermissionCatalogSeeder;
use Database\Seeders\TimezoneSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsTenantFixtures;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
final class EventOrganizerSelectionTest extends Phase1MySqlTestCase
{
    use BuildsTenantFixtures;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_platform_admin_must_select_organizer_when_creating_event(): void
    {
        $fixture = $this->seedOrganizerFixture();
        $platformAdmin = $fixture['platformAdmin'];
        $organizer = $fixture['organizer'];

        $this->actingAsTenantMember($platformAdmin, $fixture['tenant']);

        $this->postJson('/api/v1/tenant/events', $this->eventPayload(), $this->requestHeaders($fixture['tenant']))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'organizer_required');

        $this->postJson('/api/v1/tenant/events', [
            ...$this->eventPayload(),
            'organizer_user_id' => $organizer->id,
        ], $this->requestHeaders($fixture['tenant']))
            ->assertCreated();

        $event = Event::query()->where('tenant_id', $fixture['tenant']->id)->where('slug', 'organizer-selection-test')->firstOrFail();

        self::assertSame($organizer->id, $event->created_by_user_id);
    }

    public function test_tenant_organizer_defaults_event_owner_to_self(): void
    {
        $fixture = $this->seedOrganizerFixture();
        $organizer = $fixture['organizer'];

        $this->actingAsTenantMember($organizer, $fixture['tenant']);

        $this->postJson('/api/v1/tenant/events', $this->eventPayload('self-owned-event'), $this->requestHeaders($fixture['tenant']))
            ->assertCreated();

        $event = Event::query()->where('tenant_id', $fixture['tenant']->id)->where('slug', 'self-owned-event')->firstOrFail();

        self::assertSame($organizer->id, $event->created_by_user_id);
    }

    /** @return array{platformAdmin:User,organizer:User,tenant:Tenant} */
    private function seedOrganizerFixture(): array
    {
        $this->seed(PermissionCatalogSeeder::class);
        $this->seed(TimezoneSeeder::class);

        $fixture = $this->createTenantMember();
        $platformAdmin = User::factory()->create();
        $organizer = User::factory()->create();

        $organizerMembership = TenantMembership::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'user_id' => $organizer->id,
            'status' => 'active',
            'created_by_user_id' => $platformAdmin->id,
        ]);

        $this->grantTenantPermissions($fixture['tenant'], $fixture['membership'], ['event.manage', 'membership.view']);
        $this->grantTenantPermissions($fixture['tenant'], $organizerMembership, ['event.manage']);
        $this->grantPlatformPermissions($platformAdmin, ['platform.tenant.manage', 'platform.tenant.view']);

        TenantMembership::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'user_id' => $platformAdmin->id,
            'status' => 'active',
            'created_by_user_id' => $platformAdmin->id,
        ]);

        $this->grantTenantPermissions($fixture['tenant'], TenantMembership::query()
            ->where('tenant_id', $fixture['tenant']->id)
            ->where('user_id', $platformAdmin->id)
            ->firstOrFail(), ['event.manage']);

        return [
            'platformAdmin' => $platformAdmin,
            'organizer' => $organizer,
            'tenant' => $fixture['tenant'],
        ];
    }

    /** @param list<string> $permissions */
    private function grantPlatformPermissions(User $user, array $permissions): void
    {
        $role = PlatformRole::query()->create([
            'name' => 'Platform test '.Str::lower((string) Str::ulid()),
            'description' => 'Platform test role',
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

        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->id,
            'platform_role_id' => $role->id,
            'granted_by_user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string,mixed> */
    private function eventPayload(string $slug = 'organizer-selection-test'): array
    {
        return [
            'slug' => $slug,
            'name' => ['en' => 'Organizer test', 'ar' => 'اختبار المنظم'],
            'description' => ['en' => null, 'ar' => null],
            'tier' => 'public',
            'timezone' => 'Africa/Cairo',
            'capacity' => 100,
            'venues' => [[
                'name' => ['en' => 'Main hall', 'ar' => 'القاعة الرئيسية'],
                'start_at' => '2027-06-01 10:00:00',
                'end_at' => '2027-06-01 18:00:00',
                'registration_opens_at' => '2027-05-01 00:00:00',
                'registration_closes_at' => '2027-06-01 09:00:00',
            ]],
        ];
    }

    /** @return array<string,string> */
    private function requestHeaders(Tenant $tenant): array
    {
        return [
            'X-Tenant-ID' => (string) $tenant->id,
            'Idempotency-Key' => (string) Str::ulid(),
        ];
    }
}
