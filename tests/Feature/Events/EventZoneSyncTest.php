<?php

namespace Tests\Feature\Events;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\City;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\Country;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\EventAgendaItem;
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
final class EventZoneSyncTest extends Phase1MySqlTestCase
{
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    #[Test]
    public function it_syncs_zones_for_a_venue_and_derives_agenda_venue_from_zone(): void
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

        $this->actingAsTenantMember($actor, $tenant);

        $zoneResponse = $this->putJson(
            "/api/v1/tenant/events/{$event->id}/zones",
            [
                'venue_id' => $venue->id,
                'zones' => [
                    [
                        'zone_name_en' => 'Hall A',
                        'zone_name_ar' => 'قاعة أ',
                        'description_en' => 'Main conference hall',
                        'description_ar' => 'القاعة الرئيسية',
                        'type' => 'hall',
                        'capacity' => 200,
                    ],
                    [
                        'zone_name_en' => 'Stage 1',
                        'zone_name_ar' => 'مسرح 1',
                        'type' => 'stage',
                        'capacity' => null,
                    ],
                ],
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $zoneResponse->assertOk()
            ->assertJsonPath('data.zones.0.zone_name_en', 'Hall A')
            ->assertJsonPath('data.zones.0.name.en', 'Hall A')
            ->assertJsonPath('data.zones.0.description_en', 'Main conference hall')
            ->assertJsonPath('data.zones.0.description_ar', 'القاعة الرئيسية')
            ->assertJsonPath('data.zones.0.type', 'hall')
            ->assertJsonPath('data.zones.1.zone_name_en', 'Stage 1')
            ->assertJsonPath('data.zones.1.description_en', null);

        self::assertSame(2, EventZone::query()->where('event_id', $event->id)->count());

        $hallZoneId = (int) $zoneResponse->json('data.zones.0.id');

        $agendaResponse = $this->putJson(
            "/api/v1/tenant/events/{$event->id}/agenda",
            [
                'items' => [
                    [
                        'zone_id' => $hallZoneId,
                        'agenda_date' => '2027-01-10',
                        'title_en' => 'Keynote',
                        'title_ar' => 'كلمة رئيسية',
                        'speaker' => 'Dr. Sara',
                        'start_at' => '2027-01-10T09:00:00',
                        'end_at' => '2027-01-10T10:00:00',
                    ],
                ],
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $agendaResponse->assertOk()
            ->assertJsonPath('data.items.0.zone_id', (string) $hallZoneId)
            ->assertJsonPath('data.items.0.event_venue_id', (string) $venue->id)
            ->assertJsonPath('data.items.0.speaker', 'Dr. Sara');

        $item = EventAgendaItem::query()->where('event_id', $event->id)->firstOrFail();
        self::assertSame((int) $venue->id, (int) $item->event_venue_id);
        self::assertSame($hallZoneId, (int) $item->zone_id);
        self::assertSame('Dr. Sara', $item->speaker);

        $deleteResponse = $this->putJson(
            "/api/v1/tenant/events/{$event->id}/zones",
            [
                'venue_id' => $venue->id,
                'zones' => [
                    [
                        'zone_name_en' => 'Stage 1',
                        'zone_name_ar' => 'مسرح 1',
                        'type' => 'stage',
                        'capacity' => null,
                    ],
                ],
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $deleteResponse->assertOk()->assertJsonCount(1, 'data.zones');
        self::assertSame(1, EventZone::query()->where('event_id', $event->id)->count());
        self::assertNull(EventAgendaItem::query()->where('event_id', $event->id)->value('zone_id'));
        self::assertSame((int) $venue->id, (int) EventAgendaItem::query()->where('event_id', $event->id)->value('event_venue_id'));
    }

    #[Test]
    public function it_accepts_rectangle_with_closed_ring_duplicate_and_coerces_extra_vertices_to_polygon(): void
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

        $this->actingAsTenantMember($actor, $tenant);

        $closedRing = $this->putJson(
            "/api/v1/tenant/events/{$event->id}/zones",
            [
                'venue_id' => $venue->id,
                'zones' => [
                    [
                        'zone_name_en' => 'Hall',
                        'zone_name_ar' => 'قاعة',
                        'type' => 'hall',
                        'shape_type' => 'rectangle',
                        'coordinate_space' => 'geo',
                        'polygon_coordinates' => [
                            ['lat' => 24.7140, 'lng' => 46.6750],
                            ['lat' => 24.7140, 'lng' => 46.6760],
                            ['lat' => 24.7130, 'lng' => 46.6760],
                            ['lat' => 24.7130, 'lng' => 46.6750],
                            ['lat' => 24.7140, 'lng' => 46.6750],
                        ],
                        'shape_rotation' => 15,
                    ],
                ],
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $closedRing->assertOk()
            ->assertJsonPath('data.zones.0.shape_type', 'rectangle');
        self::assertCount(4, $closedRing->json('data.zones.0.polygon_coordinates'));

        $extraVertex = $this->putJson(
            "/api/v1/tenant/events/{$event->id}/zones",
            [
                'venue_id' => $venue->id,
                'zones' => [
                    [
                        'id' => (int) $closedRing->json('data.zones.0.id'),
                        'zone_name_en' => 'Hall',
                        'zone_name_ar' => 'قاعة',
                        'type' => 'hall',
                        'shape_type' => 'rectangle',
                        'coordinate_space' => 'geo',
                        'polygon_coordinates' => [
                            ['lat' => 24.7140, 'lng' => 46.6750],
                            ['lat' => 24.7140, 'lng' => 46.6760],
                            ['lat' => 24.7135, 'lng' => 46.6765],
                            ['lat' => 24.7130, 'lng' => 46.6760],
                            ['lat' => 24.7130, 'lng' => 46.6750],
                        ],
                        'shape_rotation' => 15,
                    ],
                ],
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $extraVertex->assertOk()
            ->assertJsonPath('data.zones.0.shape_type', 'polygon');
        self::assertCount(5, $extraVertex->json('data.zones.0.polygon_coordinates'));
    }

    #[Test]
    public function it_syncs_floor_type_and_floor_number_for_zones(): void
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

        $this->actingAsTenantMember($actor, $tenant);

        $response = $this->putJson(
            "/api/v1/tenant/events/{$event->id}/zones",
            [
                'venue_id' => $venue->id,
                'zones' => [
                    [
                        'zone_name_en' => 'Basement Hall',
                        'zone_name_ar' => 'قاعة القبو',
                        'type' => 'hall',
                        'floor_type' => 'basement',
                    ],
                    [
                        'zone_name_en' => 'Floor 2 Room',
                        'zone_name_ar' => 'غرفة الطابق 2',
                        'type' => 'room',
                        'floor_type' => 'floor',
                        'floor_number' => 2,
                    ],
                ],
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $response->assertOk()
            ->assertJsonPath('data.zones.0.floor_type', 'basement')
            ->assertJsonPath('data.zones.0.floor_number', null)
            ->assertJsonPath('data.zones.1.floor_type', 'floor')
            ->assertJsonPath('data.zones.1.floor_number', 2);

        $missingFloorNumber = $this->putJson(
            "/api/v1/tenant/events/{$event->id}/zones",
            [
                'venue_id' => $venue->id,
                'zones' => [
                    [
                        'zone_name_en' => 'Bad Floor',
                        'zone_name_ar' => 'طابق خاطئ',
                        'type' => 'hall',
                        'floor_type' => 'floor',
                    ],
                ],
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $missingFloorNumber->assertStatus(422);
    }
}
