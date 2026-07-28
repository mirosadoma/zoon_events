<?php

namespace Tests\Feature\Events;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\City;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\Country;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\EventPath;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsTenantFixtures;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
final class EventPathSyncTest extends Phase1MySqlTestCase
{
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    #[Test]
    public function it_syncs_indoor_paths_and_links_zones(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $fixture = $this->createRegistrationFixture();
        $actor = $fixture['actor'];
        $tenant = $fixture['tenant'];
        $event = $fixture['event'];

        $membership = TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $actor->id,
            'status' => 'active',
            'created_by_user_id' => $actor->id,
        ]);
        $this->grantTenantPermissions($tenant, $membership, ['event.manage', 'event.view']);

        $country = Country::query()->first() ?? Country::query()->create([
            'code' => 'SA',
            'name_en' => 'Saudi Arabia',
            'name_ar' => 'السعودية',
        ]);
        $city = City::query()->where('country_id', $country->id)->first() ?? City::query()->create([
            'country_id' => $country->id,
            'name_en' => 'Riyadh',
            'name_ar' => 'الرياض',
        ]);

        $venue = EventVenue::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'country_id' => $country->id,
            'city_id' => $city->id,
            'name_en' => 'Main Venue',
            'name_ar' => 'الموقع الرئيسي',
            'location_address' => 'King Fahd Rd',
            'start_at' => '2027-01-10 08:00:00',
            'end_at' => '2027-01-10 18:00:00',
            'sort_order' => 0,
        ]);

        $fromZone = EventZone::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'zone_name_en' => 'Entrance',
            'zone_name_ar' => 'المدخل',
            'type' => 'other',
        ]);
        $toZone = EventZone::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'zone_name_en' => 'Hall A',
            'zone_name_ar' => 'قاعة أ',
            'type' => 'hall',
        ]);

        $this->actingAsTenantMember($actor, $tenant);

        $response = $this->putJson(
            "/api/v1/tenant/events/{$event->id}/paths",
            [
                'venue_id' => $venue->id,
                'paths' => [
                    [
                        'name_en' => 'Entrance to Hall',
                        'name_ar' => 'من المدخل للقاعة',
                        'polyline_coordinates' => [
                            ['x' => 0.1, 'y' => 0.2],
                            ['x' => 0.4, 'y' => 0.5],
                            ['x' => 0.7, 'y' => 0.6],
                        ],
                        'from_zone_id' => $fromZone->id,
                        'to_zone_id' => $toZone->id,
                        'stroke_color' => '#2563eb',
                        'stroke_width' => 4,
                        'opacity' => 90,
                    ],
                ],
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $response->assertOk()
            ->assertJsonPath('data.paths.0.name_en', 'Entrance to Hall')
            ->assertJsonPath('data.paths.0.from_zone_id', (string) $fromZone->id)
            ->assertJsonPath('data.paths.0.to_zone_id', (string) $toZone->id)
            ->assertJsonCount(3, 'data.paths.0.polyline_coordinates');

        self::assertSame(1, EventPath::query()->where('event_id', $event->id)->count());
    }

    #[Test]
    public function it_allows_syncing_an_empty_paths_list(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $fixture = $this->createRegistrationFixture();
        $actor = $fixture['actor'];
        $tenant = $fixture['tenant'];
        $event = $fixture['event'];

        $membership = TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $actor->id,
            'status' => 'active',
            'created_by_user_id' => $actor->id,
        ]);
        $this->grantTenantPermissions($tenant, $membership, ['event.manage', 'event.view']);

        $country = Country::query()->first() ?? Country::query()->create([
            'code' => 'SA',
            'name_en' => 'Saudi Arabia',
            'name_ar' => 'السعودية',
        ]);
        $city = City::query()->where('country_id', $country->id)->first() ?? City::query()->create([
            'country_id' => $country->id,
            'name_en' => 'Riyadh',
            'name_ar' => 'الرياض',
        ]);

        $venue = EventVenue::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'country_id' => $country->id,
            'city_id' => $city->id,
            'name_en' => 'Main Venue',
            'name_ar' => 'الموقع الرئيسي',
            'location_address' => 'King Fahd Rd',
            'start_at' => '2027-01-10 08:00:00',
            'end_at' => '2027-01-10 18:00:00',
            'sort_order' => 0,
        ]);

        EventPath::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'name_en' => 'Old path',
            'name_ar' => 'مسار قديم',
            'polyline_coordinates' => [
                ['x' => 0.1, 'y' => 0.2],
                ['x' => 0.4, 'y' => 0.5],
            ],
            'coordinate_space' => 'relative',
            'stroke_color' => '#2563eb',
            'stroke_width' => 3,
            'opacity' => 85,
            'sort_order' => 0,
        ]);

        $this->actingAsTenantMember($actor, $tenant);

        $response = $this->putJson(
            "/api/v1/tenant/events/{$event->id}/paths",
            [
                'venue_id' => $venue->id,
                'paths' => [],
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $response->assertOk()
            ->assertJsonCount(0, 'data.paths');

        self::assertSame(0, EventPath::query()->where('event_id', $event->id)->where('venue_id', $venue->id)->count());
    }
}
