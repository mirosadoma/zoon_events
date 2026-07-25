<?php

namespace Tests\Feature\Events;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\City;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\Country;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventAgendaItem;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategory;
use App\Modules\Events\Infrastructure\Persistence\Models\EventEmailTemplate;
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

        EventAgendaItem::query()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $source->id,
            'event_venue_id' => $sourceVenue->id,
            'agenda_date' => '2027-01-10',
            'title_en' => 'Opening',
            'title_ar' => 'الافتتاح',
            'description_en' => 'Welcome',
            'description_ar' => 'ترحيب',
            'start_at' => '2027-01-10 09:00:00',
            'end_at' => '2027-01-10 10:00:00',
            'sort_order' => 1,
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

        $agenda = EventAgendaItem::query()->where('event_id', $clone->id)->firstOrFail();
        self::assertSame((int) $clonedVenue->id, (int) $agenda->event_venue_id);
        self::assertSame('Opening', $agenda->title_en);

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
