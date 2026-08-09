<?php

namespace Tests\Unit\AdminConsole;

use App\Modules\AdminConsole\Application\Actions\SyncEventVenues;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\City;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\Country;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\TestCase;

#[Group('phase-1')]
final class SyncEventVenuesTest extends TestCase
{
    use CreatesPhase1RegistrationFixture;
    use RefreshDatabase;

    public function test_sync_accepts_nested_name_payload_and_preserves_venues(): void
    {
        $fixture = $this->createRegistrationFixture();
        /** @var Event $event */
        $event = $fixture['event'];

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

        (new SyncEventVenues)->execute((string) $event->tenant_id, $event, [
            [
                'country_id' => $country->id,
                'city_id' => $city->id,
                'name' => ['en' => 'Main Hall', 'ar' => 'القاعة الرئيسية'],
                'location_address' => 'King Fahd Rd',
                'latitude' => 24.7,
                'longitude' => 46.7,
                'start_at' => '2026-08-01 09:00:00',
                'end_at' => '2026-08-01 18:00:00',
                'registration_opens_at' => '2026-07-01 09:00:00',
                'registration_closes_at' => '2026-07-31 18:00:00',
            ],
        ]);

        $this->assertDatabaseCount('event_venues', 1);
        $this->assertDatabaseHas('event_venues', [
            'event_id' => $event->id,
            'name_en' => 'Main Hall',
            'name_ar' => 'القاعة الرئيسية',
        ]);

        $stored = EventVenue::query()->where('event_id', $event->id)->firstOrFail();
        // Fixture event timezone is Africa/Cairo (UTC+2/+3). 09:00 local → 06:00 or 07:00 UTC.
        self::assertSame(
            \App\Modules\Events\Application\Support\EventWallClockDateTime::parseToAppStorage(
                '2026-08-01 09:00:00',
                (string) $event->timezone,
            )?->toDateTimeString(),
            $stored->start_at?->timezone('UTC')->format('Y-m-d H:i:s'),
        );

        $existing = EventVenue::query()->where('event_id', $event->id)->firstOrFail();

        (new SyncEventVenues)->execute((string) $event->tenant_id, $event, [
            [
                'id' => $existing->id,
                'country_id' => $country->id,
                'city_id' => $city->id,
                'name' => ['en' => 'Main Hall Updated', 'ar' => 'القاعة الرئيسية محدث'],
                'location_address' => 'King Fahd Rd',
                'latitude' => 24.7,
                'longitude' => 46.7,
                'start_at' => '2026-08-01 09:00:00',
                'end_at' => '2026-08-01 18:00:00',
                'registration_opens_at' => '2026-07-01 09:00:00',
                'registration_closes_at' => '2026-07-31 18:00:00',
            ],
        ]);

        $this->assertDatabaseCount('event_venues', 1);
        $this->assertDatabaseHas('event_venues', [
            'id' => $existing->id,
            'name_en' => 'Main Hall Updated',
        ]);
    }

    public function test_sync_rejects_payload_without_usable_names(): void
    {
        $fixture = $this->createRegistrationFixture();
        /** @var Event $event */
        $event = $fixture['event'];

        $this->expectException(InvalidArgumentException::class);

        (new SyncEventVenues)->execute((string) $event->tenant_id, $event, [
            ['name' => ['en' => '', 'ar' => '']],
        ]);
    }
}
