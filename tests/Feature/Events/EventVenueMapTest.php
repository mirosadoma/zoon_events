<?php

namespace Tests\Feature\Events;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\City;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\Country;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\EventVenueMap;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsTenantFixtures;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
final class EventVenueMapTest extends Phase1MySqlTestCase
{
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    #[Test]
    public function it_uploads_a_venue_map_and_syncs_relative_zone_shapes(): void
    {
        Storage::fake('public');
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
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'start_at' => '2027-01-10 08:00:00',
            'end_at' => '2027-01-10 18:00:00',
            'sort_order' => 0,
        ]);

        $this->actingAsTenantMember($actor, $tenant);

        $upload = $this->post(
            "/api/v1/tenant/events/{$event->id}/venues/{$venue->id}/map",
            [
                'image' => UploadedFile::fake()->image('floorplan.png', 1200, 800),
                'width' => 1200,
                'height' => 800,
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
            ],
        );

        $upload->assertOk()
            ->assertJsonPath('data.map.width', 1200)
            ->assertJsonPath('data.map.height', 800)
            ->assertJsonPath('data.map.overlay_opacity', 0.85)
            ->assertJsonPath('data.map.show_base_map', true)
            ->assertJsonPath('data.map.remove_background', false)
            ->assertJsonPath('data.venue.id', (string) $venue->id);

        self::assertSame(1, EventVenueMap::query()->where('venue_id', $venue->id)->count());

        $settings = $this->patchJson(
            "/api/v1/tenant/events/{$event->id}/venues/{$venue->id}/map/settings",
            [
                'overlay_opacity' => 0.55,
                'remove_background' => true,
                'show_base_map' => true,
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $settings->assertOk()
            ->assertJsonPath('data.map.overlay_opacity', 0.55)
            ->assertJsonPath('data.map.remove_background', true)
            ->assertJsonPath('data.map.show_base_map', true);

        $sync = $this->putJson(
            "/api/v1/tenant/events/{$event->id}/zones",
            [
                'venue_id' => $venue->id,
                'zones' => [
                    [
                        'zone_name_en' => 'VIP Parking',
                        'zone_name_ar' => 'موقف كبار الشخصيات',
                        'type' => 'parking',
                        'shape_type' => 'polygon',
                        'polygon_coordinates' => [
                            ['x' => 0.25, 'y' => 0.40],
                            ['x' => 0.40, 'y' => 0.40],
                            ['x' => 0.40, 'y' => 0.55],
                            ['x' => 0.25, 'y' => 0.55],
                        ],
                        'label' => 'VIP Parking',
                        'lat' => 24.7140,
                        'lng' => 46.6760,
                        'fill_color' => '#9333ea',
                        'opacity' => 60,
                        'stroke_width' => 2,
                    ],
                ],
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $sync->assertOk()
            ->assertJsonPath('data.zones.0.type', 'parking')
            ->assertJsonPath('data.zones.0.shape_type', 'polygon')
            ->assertJsonPath('data.zones.0.polygon_coordinates.0.x', 0.25)
            ->assertJsonPath('data.zones.0.polygon_coordinates.0.y', 0.4);

        $zone = EventZone::query()->where('venue_id', $venue->id)->firstOrFail();
        self::assertSame('polygon', $zone->shape_type?->value);
        self::assertSame(0.25, (float) $zone->polygon_coordinates[0]['x']);

        $show = $this->getJson(
            "/api/v1/tenant/events/{$event->id}/venues/{$venue->id}/map",
            ['X-Tenant-ID' => (string) $tenant->id],
        );

        $show->assertOk()
            ->assertJsonPath('data.zones.0.label', 'VIP Parking')
            ->assertJsonPath('data.map.width', 1200);

        $event->forceFill(['status' => 'published'])->save();

        $public = $this->getJson(
            "http://register.example.test/api/v1/public/events/{$event->slug}/venues/{$venue->id}/map",
        );
        $public->assertOk();
        $navigateUrl = (string) $public->json('data.zones.0.navigate_url');
        self::assertStringContainsString('https://www.google.com/maps/dir/?api=1&destination=', $navigateUrl);
        self::assertStringContainsString('24.714', $navigateUrl);
        self::assertStringContainsString('46.676', $navigateUrl);

        // Venue form style sync without map fields must preserve geometry.
        $preserve = $this->putJson(
            "/api/v1/tenant/events/{$event->id}/zones",
            [
                'venue_id' => $venue->id,
                'zones' => [
                    [
                        'id' => $zone->id,
                        'zone_name_en' => 'VIP Parking',
                        'zone_name_ar' => 'موقف كبار الشخصيات',
                        'type' => 'parking',
                        'capacity' => 40,
                    ],
                ],
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $preserve->assertOk();
        $zone->refresh();
        self::assertSame('polygon', $zone->shape_type?->value);
        self::assertSame(40, $zone->capacity);
        self::assertSame(0.25, (float) $zone->polygon_coordinates[0]['x']);
    }

    #[Test]
    public function it_persists_map_camera_and_geo_zones_without_an_image(): void
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
            'name_en' => 'Geo Venue',
            'name_ar' => 'موقع جغرافي',
            'location_address' => 'Olaya St',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'start_at' => '2027-01-10 08:00:00',
            'end_at' => '2027-01-10 18:00:00',
            'sort_order' => 0,
        ]);

        $this->actingAsTenantMember($actor, $tenant);

        $settings = $this->patchJson(
            "/api/v1/tenant/events/{$event->id}/venues/{$venue->id}/map/settings",
            [
                'overlay_opacity' => 0.7,
                'remove_background' => false,
                'show_base_map' => true,
                'map_center_lat' => 24.7140,
                'map_center_lng' => 46.6760,
                'map_zoom' => 19,
                'map_heading' => 45,
                'map_type' => 'satellite',
                'overlay_north' => 24.7150,
                'overlay_south' => 24.7130,
                'overlay_east' => 46.6770,
                'overlay_west' => 46.6750,
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $settings->assertOk()
            ->assertJsonPath('data.map.map_center_lat', 24.714)
            ->assertJsonPath('data.map.map_center_lng', 46.676)
            ->assertJsonPath('data.map.map_zoom', 19)
            ->assertJsonPath('data.map.map_heading', 45)
            ->assertJsonPath('data.map.map_type', 'satellite')
            ->assertJsonPath('data.map.overlay_north', 24.715);

        $sync = $this->putJson(
            "/api/v1/tenant/events/{$event->id}/zones",
            [
                'venue_id' => $venue->id,
                'zones' => [
                    [
                        'zone_name_en' => 'Hall Geo',
                        'zone_name_ar' => 'قاعة جغرافية',
                        'type' => 'hall',
                        'shape_type' => 'polygon',
                        'coordinate_space' => 'geo',
                        'polygon_coordinates' => [
                            ['lat' => 24.7142, 'lng' => 46.6755],
                            ['lat' => 24.7144, 'lng' => 46.6755],
                            ['lat' => 24.7144, 'lng' => 46.6758],
                            ['lat' => 24.7142, 'lng' => 46.6758],
                        ],
                        'label' => 'Hall Geo',
                        'fill_color' => '#7c3aed',
                        'opacity' => 50,
                        'stroke_width' => 2,
                    ],
                ],
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $sync->assertOk()
            ->assertJsonPath('data.zones.0.coordinate_space', 'geo')
            ->assertJsonPath('data.zones.0.polygon_coordinates.0.lat', 24.7142)
            ->assertJsonPath('data.zones.0.polygon_coordinates.0.lng', 46.6755);

        $zone = EventZone::query()->where('venue_id', $venue->id)->firstOrFail();
        self::assertSame('geo', (string) $zone->coordinate_space);
        self::assertSame(24.7142, (float) $zone->polygon_coordinates[0]['lat']);

        Storage::fake('public');

        $upload = $this->post(
            "/api/v1/tenant/events/{$event->id}/venues/{$venue->id}/map",
            [
                'image' => UploadedFile::fake()->image('floorplan.png', 1200, 800),
                'width' => 1200,
                'height' => 800,
                'map_center_lat' => 24.7140,
                'map_center_lng' => 46.6760,
                'map_zoom' => 19,
                'overlay_north' => 24.7150,
                'overlay_south' => 24.7130,
                'overlay_east' => 46.6770,
                'overlay_west' => 46.6750,
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
            ],
        );

        $upload->assertOk()
            ->assertJsonPath('data.map.width', 1200);

        $zone->refresh();
        self::assertSame('geo', (string) $zone->coordinate_space);
        self::assertSame(24.7142, (float) $zone->polygon_coordinates[0]['lat']);
        self::assertSame(46.6755, (float) $zone->polygon_coordinates[0]['lng']);
    }

    #[Test]
    public function it_converts_relative_zones_to_geo_when_overlay_bounds_exist(): void
    {
        Storage::fake('public');
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
            'name_en' => 'Legacy Venue',
            'name_ar' => 'موقع قديم',
            'location_address' => 'King Fahd Rd',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'start_at' => '2027-01-10 08:00:00',
            'end_at' => '2027-01-10 18:00:00',
            'sort_order' => 0,
        ]);

        EventVenueMap::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'image_path' => 'tenants/legacy/floor.png',
            'width' => 1000,
            'height' => 800,
            'overlay_opacity' => 0.85,
            'remove_background' => false,
            'show_base_map' => true,
            'map_center_lat' => 24.7136,
            'map_center_lng' => 46.6753,
            'map_zoom' => 18,
            'map_heading' => 0,
            'map_type' => 'hybrid',
        ]);

        $zone = EventZone::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'zone_name_en' => 'Relative Hall',
            'zone_name_ar' => 'قاعة نسبية',
            'type' => 'hall',
            'shape_type' => 'rectangle',
            'coordinate_space' => 'relative',
            'polygon_coordinates' => [
                ['x' => 0.0, 'y' => 0.0],
                ['x' => 1.0, 'y' => 0.0],
                ['x' => 1.0, 'y' => 1.0],
                ['x' => 0.0, 'y' => 1.0],
            ],
            'shape_rotation' => 0,
            'fill_color' => '#7c3aed',
            'opacity' => 45,
            'stroke_width' => 2,
        ]);

        $this->actingAsTenantMember($actor, $tenant);

        $settings = $this->patchJson(
            "/api/v1/tenant/events/{$event->id}/venues/{$venue->id}/map/settings",
            [
                'overlay_north' => 24.7146,
                'overlay_south' => 24.7126,
                'overlay_east' => 46.6763,
                'overlay_west' => 46.6743,
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $settings->assertOk();

        $zone->refresh();
        self::assertSame('geo', (string) $zone->coordinate_space);
        self::assertSame(24.7146, (float) $zone->polygon_coordinates[0]['lat']);
        self::assertSame(46.6743, (float) $zone->polygon_coordinates[0]['lng']);
        self::assertSame(24.7126, (float) $zone->polygon_coordinates[2]['lat']);
        self::assertSame(46.6763, (float) $zone->polygon_coordinates[2]['lng']);
    }

    #[Test]
    public function it_deletes_the_floor_plan_image_but_keeps_camera_settings(): void
    {
        Storage::fake('public');
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
            'name_en' => 'Delete Image Venue',
            'name_ar' => 'موقع حذف الصورة',
            'location_address' => 'Olaya St',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'start_at' => '2027-01-10 08:00:00',
            'end_at' => '2027-01-10 18:00:00',
            'sort_order' => 0,
        ]);

        $this->actingAsTenantMember($actor, $tenant);

        $upload = $this->post(
            "/api/v1/tenant/events/{$event->id}/venues/{$venue->id}/map",
            [
                'image' => UploadedFile::fake()->image('floorplan.png', 800, 600),
                'width' => 800,
                'height' => 600,
                'map_center_lat' => 24.714,
                'map_center_lng' => 46.676,
                'map_zoom' => 19,
            ],
            ['X-Tenant-ID' => (string) $tenant->id],
        );
        $upload->assertOk();

        $map = EventVenueMap::query()->where('venue_id', $venue->id)->firstOrFail();
        self::assertNotSame('', (string) $map->image_path);
        Storage::disk('public')->assertExists($map->image_path);

        $delete = $this->deleteJson(
            "/api/v1/tenant/events/{$event->id}/venues/{$venue->id}/map",
            [],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $delete->assertOk()
            ->assertJsonPath('data.map.image_url', null)
            ->assertJsonPath('data.map.map_center_lat', 24.714)
            ->assertJsonPath('data.map.map_zoom', 19);

        $map->refresh();
        self::assertSame('', (string) $map->image_path);
        self::assertNull($map->width);
        self::assertSame(24.714, (float) $map->map_center_lat);
    }
}
