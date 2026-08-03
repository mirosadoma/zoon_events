<?php

namespace Tests\Feature\Scanning;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\City;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\Country;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScannerAppSession;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('scanning')]
#[Group('scanner-app')]
final class ScannerAppApiTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_login_scan_and_logout_with_zone_code(): void
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
            'scanner_code' => '12345678',
            'capacity' => 100,
        ]);

        $login = $this->postJson('/api/v1/scanner-app/login', [
            'scanner_code' => '12345678',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.zone.id', (string) $zone->id)
            ->assertJsonPath('data.zone.scanner_code', '12345678')
            ->assertJsonPath('data.event.id', (string) $event->id);

        $token = $login->json('data.token');
        self::assertIsString($token);
        self::assertNotSame('', $token);

        $headers = [
            'Authorization' => 'ScannerApp '.$token,
            'Idempotency-Key' => (string) Str::ulid(),
        ];

        $this->getJson('/api/v1/scanner-app/me', $headers)
            ->assertOk()
            ->assertJsonPath('data.zone.id', (string) $zone->id);

        $this->postJson('/api/v1/scanner-app/scan', [
            'credential_id' => (string) $scan['credential']->id,
        ], $headers)->assertOk()
            ->assertJsonPath('data.result', 'accepted');

        self::assertDatabaseHas('scan_events', [
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'zone_id' => (string) $zone->id,
            'credential_id' => $scan['credential']->id,
            'result' => 'accepted',
        ]);

        $this->postJson('/api/v1/scanner-app/logout', [], [
            'Authorization' => 'ScannerApp '.$token,
        ])->assertOk();

        $this->getJson('/api/v1/scanner-app/me', [
            'Authorization' => 'ScannerApp '.$token,
        ])->assertStatus(401);

        self::assertNotNull(
            ScannerAppSession::query()->where('token_hash', hash('sha256', $token))->value('revoked_at')
        );
    }
}
