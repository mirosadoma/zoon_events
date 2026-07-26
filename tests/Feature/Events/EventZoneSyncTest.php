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
            ->assertJsonPath('data.zones.0.type', 'hall')
            ->assertJsonPath('data.zones.1.zone_name_en', 'Stage 1');

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
}
