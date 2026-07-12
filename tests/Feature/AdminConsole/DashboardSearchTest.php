<?php

namespace Tests\Feature\AdminConsole;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
final class DashboardSearchTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_tenant_member_can_search_events_by_name(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('email', 'demo@zonetec.test')->firstOrFail();

        $response = $this->actingAs($user)
            ->getJson('/en/dashboard/search?q=Summit');

        $response->assertOk()
            ->assertJsonPath('results.0.type', 'event')
            ->assertJsonPath('results.0.label', 'Zonetec Summit 2026')
            ->assertJsonPath('results.0.href', fn (string $href): bool => str_contains($href, '/tenant/events/'));
    }

    public function test_tenant_member_can_search_events_by_partial_prefix(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('email', 'demo@zonetec.test')->firstOrFail();

        $response = $this->actingAs($user)
            ->getJson('/en/dashboard/search?q=Zon');

        $response->assertOk()
            ->assertJsonPath('results.0.type', 'event')
            ->assertJsonPath('results.0.label', 'Zonetec Summit 2026');
    }

    public function test_tenant_member_can_search_events_from_single_character(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('email', 'demo@zonetec.test')->firstOrFail();

        $response = $this->actingAs($user)
            ->getJson('/en/dashboard/search?q=Z');

        $response->assertOk()
            ->assertJsonPath('results.0.type', 'event')
            ->assertJsonPath('results.0.label', 'Zonetec Summit 2026');
    }

    public function test_platform_admin_can_search_events_across_tenants(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('email', 'super.admin@admin.com')->firstOrFail();

        $response = $this->actingAs($user)
            ->getJson('/en/dashboard/search?q=Z');

        $response->assertOk()
            ->assertJsonPath('results.0.type', 'event')
            ->assertJsonPath('results.0.label', 'Zonetec Summit 2026')
            ->assertJsonPath('results.0.tenant_name', 'Fixture Alpha');
    }

    public function test_search_includes_main_image_url_when_present(): void
    {
        Storage::fake('public');

        $this->seed(\Database\Seeders\PermissionCatalogSeeder::class);

        $fixture = $this->createRegistrationFixture();
        $membership = \App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'user_id' => $fixture['actor']->id,
            'status' => 'active',
            'created_by_user_id' => $fixture['actor']->id,
        ]);
        $this->grantTenantPermissions($fixture['tenant'], $membership, ['event.view']);

        $path = 'events/search-cover.jpg';
        Storage::disk('public')->put($path, 'image-bytes');

        $fixture['event']->forceFill([
            'name_en' => 'Searchable Gala',
            'main_image_path' => $path,
        ])->save();

        $response = $this->actingAs($fixture['actor'])
            ->getJson('/en/dashboard/search?q=Gala');

        $response->assertOk()
            ->assertJsonPath('results.0.type', 'event')
            ->assertJsonPath('results.0.label', 'Searchable Gala')
            ->assertJsonPath('results.0.main_image', Storage::disk('public')->url($path));
    }

    public function test_guest_search_is_rejected(): void
    {
        $this->getJson('/en/dashboard/search?q=Summit')
            ->assertUnauthorized();
    }
}
