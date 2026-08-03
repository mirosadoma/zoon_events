<?php

namespace Tests\Feature\Scanning;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\City;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\Country;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('scanning')]
#[Group('zone-occupancy')]
final class ZoneOccupancyTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_summary_counts_latest_zone_scoped_scan_per_attendee(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture([
            'checkin.scan.submit',
            'checkin.dashboard.view',
        ]);
        $event = $scan['fixture']['event'];
        $tenant = $scan['fixture']['tenant'];
        $attendeeId = (string) $scan['credential']->attendee_id;

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
            'name_en' => 'Main',
            'name_ar' => 'رئيسي',
            'location_address' => 'Test',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(2),
            'sort_order' => 0,
        ]);

        $zoneA = EventZone::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'zone_name_en' => 'Hall A',
            'zone_name_ar' => 'قاعة أ',
            'type' => 'hall',
            'scanner_code' => '11111111',
            'capacity' => 10,
        ]);
        $zoneB = EventZone::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'zone_name_en' => 'Hall B',
            'zone_name_ar' => 'قاعة ب',
            'type' => 'hall',
            'scanner_code' => '22222222',
            'capacity' => 20,
        ]);

        ScanEvent::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'attendee_id' => $attendeeId,
            'credential_id' => $scan['credential']->id,
            'scanner_id' => $scan['scanner']->id,
            'scanner_type' => 'staff_phone',
            'zone_id' => (string) $zoneA->id,
            'direction' => 'in',
            'result' => 'accepted',
            'reason' => null,
            'offline_mode' => false,
            'scanned_at' => now()->subMinutes(5),
        ]);

        $this->actingAsScanner($scan);
        $summaryUrl = "/api/v1/tenant/events/{$event->id}/zone-occupancy/summary";

        $first = $this->getJson($summaryUrl, $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.totals.inside', 1)
            ->assertJsonPath('data.totals.tracked_zones', 2);

        $zones = collect($first->json('data.zones'))->keyBy('event_zone_id');
        self::assertSame(1, (int) $zones[(string) $zoneA->id]['inside_count']);
        self::assertSame(0, (int) $zones[(string) $zoneB->id]['inside_count']);

        ScanEvent::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'attendee_id' => $attendeeId,
            'credential_id' => $scan['credential']->id,
            'scanner_id' => $scan['scanner']->id,
            'scanner_type' => 'staff_phone',
            'zone_id' => (string) $zoneB->id,
            'direction' => 'in',
            'result' => 'accepted',
            'reason' => null,
            'offline_mode' => false,
            'scanned_at' => now()->subMinute(),
        ]);

        // Zoneless accepted scan must not move occupancy.
        ScanEvent::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'attendee_id' => $attendeeId,
            'credential_id' => $scan['credential']->id,
            'scanner_id' => $scan['scanner']->id,
            'scanner_type' => 'staff_phone',
            'zone_id' => null,
            'direction' => 'in',
            'result' => 'accepted',
            'reason' => null,
            'offline_mode' => false,
            'scanned_at' => now(),
        ]);

        $this->actingAsScanner($scan);
        $second = $this->getJson($summaryUrl, $this->tenantHeaders($tenant))->assertOk();
        $zones = collect($second->json('data.zones'))->keyBy('event_zone_id');
        self::assertSame(0, (int) $zones[(string) $zoneA->id]['inside_count']);
        self::assertSame(1, (int) $zones[(string) $zoneB->id]['inside_count']);
        self::assertSame(1, (int) $second->json('data.totals.inside'));

        $this->actingAsScanner($scan);
        $this->getJson(
            "/api/v1/tenant/events/{$event->id}/zone-occupancy/analytics",
            $this->tenantHeaders($tenant),
        )->assertOk()
            ->assertJsonPath('data.range', 'today');
    }

    public function test_web_scan_persists_zone_id_when_provided(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        $event = $scan['fixture']['event'];
        $tenant = $scan['fixture']['tenant'];

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
            'name_en' => 'Main',
            'name_ar' => 'رئيسي',
            'location_address' => 'Test',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(2),
            'sort_order' => 0,
        ]);
        $zone = EventZone::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'zone_name_en' => 'Hall A',
            'zone_name_ar' => 'قاعة أ',
            'type' => 'hall',
            'scanner_code' => '33333333',
            'capacity' => 50,
        ]);

        $this->actingAsScanner($scan);
        $this->postJson(
            "/api/v1/tenant/events/{$event->id}/scans",
            [
                'qr_payload' => $scan['token'],
                'scanner_type' => 'staff_phone',
                'zone_id' => (string) $zone->id,
            ],
            $this->scanHeaders($scan, 'zone-scan-'.Str::lower((string) Str::ulid())),
        )->assertOk()->assertJsonPath('data.result', 'accepted');

        self::assertDatabaseHas('scan_events', [
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'credential_id' => $scan['credential']->id,
            'zone_id' => (string) $zone->id,
            'result' => 'accepted',
        ]);
    }
}
