<?php

namespace Tests\Feature\Events;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\City;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\Country;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\EventAgendaItem;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
final class PublicEventAgendaPageTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_guest_can_open_agenda_page_by_event_slug(): void
    {
        $fixture = $this->createRegistrationFixture();

        EventAgendaItem::query()->create([
            'tenant_id' => $fixture['event']->tenant_id,
            'event_id' => $fixture['event']->id,
            'title_en' => 'Opening speech',
            'title_ar' => 'كلمة افتتاحية',
            'start_at' => $fixture['event']->start_at,
            'end_at' => $fixture['event']->start_at?->addMinutes(15),
            'sort_order' => 0,
        ]);

        $response = $this->get("/en/events/{$fixture['event']->slug}/agenda");

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/registration/Agenda')
                ->where('registerUrl', "/en/events/{$fixture['event']->slug}/register")
                ->has('items', 1)
                ->has('event.name'));
    }

    public function test_agenda_page_shows_only_the_selected_day(): void
    {
        $fixture = $this->createRegistrationFixture();
        $event = $fixture['event'];

        EventAgendaItem::query()->create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'agenda_date' => '2027-01-10',
            'title_en' => 'Day one',
            'title_ar' => 'اليوم الأول',
            'start_at' => '2027-01-10 09:00:00',
            'end_at' => '2027-01-10 10:00:00',
            'sort_order' => 0,
        ]);

        EventAgendaItem::query()->create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'agenda_date' => '2027-01-11',
            'title_en' => 'Day two',
            'title_ar' => 'اليوم الثاني',
            'start_at' => '2027-01-11 09:00:00',
            'end_at' => '2027-01-11 10:00:00',
            'sort_order' => 0,
        ]);

        $firstDay = $this->get("/en/events/{$event->slug}/agenda");
        $firstDay->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/registration/Agenda')
                ->where('selectedDate', '2027-01-10')
                ->has('items', 1)
                ->where('items.0.title.en', 'Day one')
                ->where('availableDates', ['2027-01-10', '2027-01-11']));

        $secondDay = $this->get("/en/events/{$event->slug}/agenda?date=2027-01-11");
        $secondDay->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('selectedDate', '2027-01-11')
                ->has('items', 1)
                ->where('items.0.title.en', 'Day two'));
    }

    public function test_agenda_page_filters_items_by_selected_venue(): void
    {
        $fixture = $this->createRegistrationFixture();
        $event = $fixture['event'];
        $tenant = $fixture['tenant'];

        $country = Country::query()->first()
            ?? Country::query()->create([
                'code' => 'SA',
                'name_en' => 'Saudi Arabia',
                'name_ar' => 'السعودية',
            ]);
        $city = City::query()->where('country_id', $country->id)->first()
            ?? City::query()->create([
                'country_id' => $country->id,
                'name_en' => 'Riyadh',
                'name_ar' => 'الرياض',
            ]);

        $hallA = EventVenue::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'country_id' => $country->id,
            'city_id' => $city->id,
            'name_en' => 'Hall A',
            'name_ar' => 'قاعة أ',
            'location_address' => 'Street 1',
            'start_at' => '2027-01-10 08:00:00',
            'end_at' => '2027-01-10 18:00:00',
            'sort_order' => 0,
        ]);
        $hallB = EventVenue::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'country_id' => $country->id,
            'city_id' => $city->id,
            'name_en' => 'Hall B',
            'name_ar' => 'قاعة ب',
            'location_address' => 'Street 2',
            'start_at' => '2027-01-10 08:00:00',
            'end_at' => '2027-01-10 18:00:00',
            'sort_order' => 1,
        ]);

        EventAgendaItem::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'event_venue_id' => $hallA->id,
            'agenda_date' => '2027-01-10',
            'title_en' => 'Hall A session',
            'title_ar' => 'جلسة قاعة أ',
            'start_at' => '2027-01-10 09:00:00',
            'end_at' => '2027-01-10 10:00:00',
            'sort_order' => 0,
        ]);
        EventAgendaItem::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'event_venue_id' => $hallB->id,
            'agenda_date' => '2027-01-10',
            'title_en' => 'Hall B session',
            'title_ar' => 'جلسة قاعة ب',
            'start_at' => '2027-01-10 11:00:00',
            'end_at' => '2027-01-10 12:00:00',
            'sort_order' => 1,
        ]);

        $filtered = $this->get("/en/events/{$event->slug}/agenda?venue_id={$hallB->id}");
        $filtered->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/registration/Agenda')
                ->where('selectedVenueId', (string) $hallB->id)
                ->has('items', 1)
                ->where('items.0.title.en', 'Hall B session'));

        $defaultVenue = $this->get("/en/events/{$event->slug}/agenda");
        $defaultVenue->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('selectedVenueId', (string) $hallA->id)
                ->has('items', 1)
                ->where('items.0.title.en', 'Hall A session'));
    }

    public function test_agenda_page_filters_items_by_selected_zone_and_exposes_speaker(): void
    {
        $fixture = $this->createRegistrationFixture();
        $event = $fixture['event'];
        $tenant = $fixture['tenant'];

        $country = Country::query()->first()
            ?? Country::query()->create([
                'code' => 'SA',
                'name_en' => 'Saudi Arabia',
                'name_ar' => 'السعودية',
            ]);
        $city = City::query()->where('country_id', $country->id)->first()
            ?? City::query()->create([
                'country_id' => $country->id,
                'name_en' => 'Riyadh',
                'name_ar' => 'الرياض',
            ]);

        $venue = EventVenue::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'country_id' => $country->id,
            'city_id' => $city->id,
            'name_en' => 'Main Hall',
            'name_ar' => 'القاعة الرئيسية',
            'location_address' => 'Street 1',
            'start_at' => '2027-01-10 08:00:00',
            'end_at' => '2027-01-10 18:00:00',
            'sort_order' => 0,
        ]);

        $hall = EventZone::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'zone_name_en' => 'Hall A',
            'zone_name_ar' => 'قاعة أ',
            'type' => 'hall',
            'capacity' => 100,
        ]);
        $stage = EventZone::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'zone_name_en' => 'Stage 1',
            'zone_name_ar' => 'مسرح 1',
            'type' => 'stage',
            'capacity' => null,
        ]);

        EventAgendaItem::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'event_venue_id' => $venue->id,
            'zone_id' => $hall->id,
            'agenda_date' => '2027-01-10',
            'title_en' => 'Hall talk',
            'title_ar' => 'حديث القاعة',
            'speaker' => 'Dr. Sara',
            'start_at' => '2027-01-10 09:00:00',
            'end_at' => '2027-01-10 10:00:00',
            'sort_order' => 0,
        ]);
        EventAgendaItem::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'event_venue_id' => $venue->id,
            'zone_id' => $stage->id,
            'agenda_date' => '2027-01-10',
            'title_en' => 'Stage talk',
            'title_ar' => 'حديث المسرح',
            'speaker' => 'Omar',
            'start_at' => '2027-01-10 11:00:00',
            'end_at' => '2027-01-10 12:00:00',
            'sort_order' => 1,
        ]);

        $filtered = $this->get("/en/events/{$event->slug}/agenda?venue_id={$venue->id}&zone_id={$stage->id}");
        $filtered->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/registration/Agenda')
                ->where('selectedZoneId', (string) $stage->id)
                ->has('items', 1)
                ->where('items.0.title.en', 'Stage talk')
                ->where('items.0.speaker', 'Omar')
                ->where('items.0.zone_name.en', 'Stage 1')
                ->has('zones', 2));
    }

    public function test_draft_event_agenda_page_is_not_found(): void
    {
        $fixture = $this->createRegistrationFixture();
        $fixture['event']->update(['status' => 'draft']);

        $this->get("/en/events/{$fixture['event']->slug}/agenda")->assertNotFound();
    }
}
