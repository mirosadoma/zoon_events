<?php

namespace Tests\Feature\Events;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\City;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\Country;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Events\Domain\EventZoneType;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventAgendaItem;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategory;
use App\Modules\Events\Infrastructure\Persistence\Models\EventEmailTemplate;
use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
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
final class CloneEventTest extends Phase1MySqlTestCase
{
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    #[Test]
    public function it_clones_event_details_and_related_setup_into_a_draft(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $fixture = $this->createRegistrationFixture();
        $actor = $fixture['actor'];
        $tenant = $fixture['tenant'];
        $source = $fixture['event'];

        $source->forceFill([
            'event_type' => 'conference',
            'registration_mode' => 'free_registration',
            'description_en' => 'Source description',
            'description_ar' => 'وصف المصدر',
        ])->save();

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

        $sourceVenue = EventVenue::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $source->id,
            'country_id' => $country->id,
            'city_id' => $city->id,
            'name_en' => 'Main Hall',
            'name_ar' => 'القاعة الرئيسية',
            'location_address' => 'King Fahd Rd',
            'start_at' => '2027-01-10 08:00:00',
            'end_at' => '2027-01-10 18:00:00',
            'registration_opens_at' => '2026-12-01 09:00:00',
            'registration_closes_at' => '2027-01-09 18:00:00',
            'sort_order' => 0,
        ]);

        $sourceZone = EventZone::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $source->id,
            'venue_id' => $sourceVenue->id,
            'zone_name_en' => 'Hall A',
            'zone_name_ar' => 'قاعة أ',
            'type' => 'hall',
            'capacity' => 250,
        ]);

        $sourceStage = EventZone::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $source->id,
            'venue_id' => $sourceVenue->id,
            'zone_name_en' => 'Stage 1',
            'zone_name_ar' => 'مسرح 1',
            'type' => 'stage',
            'capacity' => null,
        ]);

        EventAgendaItem::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $source->id,
            'event_venue_id' => $sourceVenue->id,
            'zone_id' => $sourceZone->id,
            'agenda_date' => '2027-01-10',
            'title_en' => 'Opening',
            'title_ar' => 'الافتتاح',
            'description_en' => 'Welcome',
            'description_ar' => 'ترحيب',
            'speaker' => 'Keynote Speaker',
            'start_at' => '2027-01-10 09:00:00',
            'end_at' => '2027-01-10 10:00:00',
            'sort_order' => 1,
        ]);

        EventAgendaItem::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $source->id,
            'event_venue_id' => $sourceVenue->id,
            'zone_id' => $sourceStage->id,
            'agenda_date' => '2027-01-10',
            'title_en' => 'Workshop',
            'title_ar' => 'ورشة',
            'description_en' => 'Hands-on',
            'description_ar' => 'تطبيقي',
            'speaker' => 'Omar',
            'start_at' => '2027-01-10 11:00:00',
            'end_at' => '2027-01-10 12:00:00',
            'sort_order' => 2,
        ]);

        $sourceCategory = EventCategory::query()->create([
            'event_id' => $source->id,
            'name' => 'VIP',
            'name_ar' => 'كبار الشخصيات',
            'slug' => 'vip-'.Str::lower((string) Str::ulid()),
            'color' => '#2563eb',
            'is_paid' => false,
            'price_minor' => 0,
            'currency' => 'SAR',
            'sort_order' => 0,
        ]);
        $sourceCategory->privileges()->create([
            'key' => 'general_entry',
            'label' => 'General Entry',
            'label_ar' => 'دخول عام',
            'effect' => 'allow',
            'target_type' => null,
            'target_id' => null,
        ]);
        $sourceCategoryVenue = $sourceCategory->venues()->create([
            'event_venue_id' => $sourceVenue->id,
            'sort_order' => 0,
        ]);
        $sourceCategoryVenue->days()->create([
            'date' => '2027-01-10',
            'capacity' => 100,
        ]);

        EventEmailTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $source->id,
            'type' => 'confirmation',
            'subject_en' => 'Confirmed',
            'subject_ar' => 'تم التأكيد',
            'html_body_en' => '<p>Hello {{name}} for events/'.$source->id.'/email-templates/x.png</p>',
            'html_body_ar' => '<p>مرحبا</p>',
        ]);

        BadgeTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $source->id,
            'name' => 'Main badge',
            'layout' => [['field' => 'attendee_name']],
            'paper_size' => 'CR80',
            'printer_type' => 'badge',
            'status' => 'active',
            'background_color' => '#ffffff',
            'orientation' => 'landscape',
            'canvas_width' => 400,
            'canvas_height' => 250,
        ]);

        $this->actingAsTenantMember($actor, $tenant);

        $response = $this->postJson(
            "/api/v1/tenant/events/{$source->id}/copy",
            ['name' => ['en' => 'Cloned Summit', 'ar' => 'قمة منسوخة']],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.name.en', 'Cloned Summit')
            ->assertJsonPath('data.name.ar', 'قمة منسوخة')
            ->assertJsonPath('data.status', 'draft');

        $cloneId = (string) $response->json('data.id');
        $clone = Event::query()->findOrFail($cloneId);

        self::assertSame('draft', $clone->status);
        self::assertSame('conference', $clone->event_type);
        self::assertSame('free_registration', $clone->registration_mode);
        self::assertSame('Source description', $clone->description_en);
        self::assertNotNull($clone->active_form_version_id);
        self::assertNotSame($source->active_form_version_id, $clone->active_form_version_id);
        self::assertTrue($clone->branding()->exists());
        self::assertSame(1, $clone->venues()->count());

        $clonedVenue = $clone->venues()->firstOrFail();
        self::assertSame('Main Hall', $clonedVenue->name_en);
        self::assertNotSame((int) $sourceVenue->id, (int) $clonedVenue->id);

        $clonedZones = EventZone::query()->where('event_id', $clone->id)->orderBy('id')->get();
        self::assertCount(2, $clonedZones);

        $clonedHall = $clonedZones->firstWhere('zone_name_en', 'Hall A');
        $clonedStage = $clonedZones->firstWhere('zone_name_en', 'Stage 1');
        self::assertNotNull($clonedHall);
        self::assertNotNull($clonedStage);
        self::assertSame('قاعة أ', $clonedHall->zone_name_ar);
        self::assertSame(EventZoneType::Hall, $clonedHall->type);
        self::assertSame(250, (int) $clonedHall->capacity);
        self::assertSame((int) $clonedVenue->id, (int) $clonedHall->venue_id);
        self::assertNotSame((int) $sourceZone->id, (int) $clonedHall->id);
        self::assertSame(EventZoneType::Stage, $clonedStage->type);
        self::assertNull($clonedStage->capacity);
        self::assertNotSame((int) $sourceStage->id, (int) $clonedStage->id);

        $agendaItems = EventAgendaItem::query()
            ->where('event_id', $clone->id)
            ->orderBy('sort_order')
            ->get();
        self::assertCount(2, $agendaItems);

        $opening = $agendaItems->firstWhere('title_en', 'Opening');
        $workshop = $agendaItems->firstWhere('title_en', 'Workshop');
        self::assertNotNull($opening);
        self::assertNotNull($workshop);
        self::assertSame((int) $clonedVenue->id, (int) $opening->event_venue_id);
        self::assertSame((int) $clonedHall->id, (int) $opening->zone_id);
        self::assertSame('Keynote Speaker', $opening->speaker);
        self::assertSame('Welcome', $opening->description_en);
        self::assertSame('2027-01-10', $opening->agenda_date?->toDateString());
        self::assertSame((int) $clonedVenue->id, (int) $workshop->event_venue_id);
        self::assertSame((int) $clonedStage->id, (int) $workshop->zone_id);
        self::assertSame('Omar', $workshop->speaker);

        $clonedCategory = EventCategory::query()->where('event_id', $clone->id)->firstOrFail();
        self::assertSame('VIP', $clonedCategory->name);
        self::assertSame(1, $clonedCategory->privileges()->count());
        self::assertSame(1, $clonedCategory->venues()->count());
        self::assertSame((int) $clonedVenue->id, (int) $clonedCategory->venues()->firstOrFail()->event_venue_id);
        self::assertSame(1, $clonedCategory->venues()->firstOrFail()->days()->count());
        self::assertSame(100, $clonedCategory->venues()->firstOrFail()->days()->firstOrFail()->capacity);

        self::assertSame(
            1,
            RegistrationFormVersion::query()->where('event_id', $clone->id)->where('status', 'published')->count(),
        );
        self::assertSame(
            1,
            EventEmailTemplate::query()->where('event_id', $clone->id)->where('type', 'confirmation')->count(),
        );
        self::assertStringContainsString(
            'events/'.$clone->id.'/email-templates/',
            (string) EventEmailTemplate::query()->where('event_id', $clone->id)->value('html_body_en'),
        );
        self::assertSame(
            1,
            BadgeTemplate::query()->where('event_id', $clone->id)->where('status', 'active')->count(),
        );
    }
}
