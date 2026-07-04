<?php

namespace Tests\Feature\Events;

use App\Models\User;
use App\Modules\Events\Contracts\PublicEventContextResolver;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
final class PublicEventPageTest extends Phase1MySqlTestCase
{
    use DatabaseTransactions;

    public function test_approved_host_returns_only_public_bilingual_branding_data(): void
    {
        $event = $this->publishedEvent();
        self::assertNotNull(app(PublicEventContextResolver::class)->resolve('events.example.test', $event->slug));

        $response = $this->withHeader('Accept-Language', 'ar')
            ->getJson("http://events.example.test/api/v1/public/events/{$event->slug}");

        $response->assertOk()
            ->assertJsonPath('data.name.en', 'Synthetic Summit')
            ->assertJsonPath('data.name.ar', 'قمة تجريبية')
            ->assertJsonPath('data.branding.brand_reference', 'approved-brand')
            ->assertJsonMissingPath('data.tenant_id')
            ->assertJsonMissingPath('data.created_by_user_id');
    }

    public function test_unapproved_host_unknown_slug_and_forged_tenant_header_are_uniform_not_found(): void
    {
        $event = $this->publishedEvent();

        $unapproved = $this->getJson("http://unapproved.example.test/api/v1/public/events/{$event->slug}");
        $unknown = $this
            ->withHeader('X-Tenant-ID', '01FORGEDTENANT000000000000')
            ->getJson('http://events.example.test/api/v1/public/events/unknown-event');

        $unapproved->assertNotFound()->assertJsonPath('code', 'resource_not_found');
        $unknown->assertNotFound()->assertJsonPath('code', 'resource_not_found');
        self::assertSame($unapproved->json('detail'), $unknown->json('detail'));
    }

    private function publishedEvent(): Event
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->create(['created_by_user_id' => $actor->id]);
        $event = Event::query()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'synthetic-summit',
            'name_en' => 'Synthetic Summit',
            'name_ar' => 'قمة تجريبية',
            'description_en' => 'Safe fixture',
            'description_ar' => 'بيانات آمنة',
            'tier' => 'public',
            'status' => 'published',
            'timezone' => 'Africa/Cairo',
            'start_at' => '2027-01-10 12:00:00',
            'end_at' => '2027-01-10 18:00:00',
            'registration_opens_at' => '2027-01-01 00:00:00',
            'registration_closes_at' => '2027-01-10 11:00:00',
            'capacity' => 100,
            'created_by_user_id' => $actor->id,
            'published_by_user_id' => $actor->id,
            'published_at' => now(),
        ]);
        $event->branding()->create([
            'tenant_id' => $tenant->id,
            'brand_reference' => 'approved-brand',
            'domain_reference' => 'events.example.test',
            'content_en' => ['summary' => 'Safe fixture'],
            'content_ar' => ['summary' => 'بيانات آمنة'],
            'sender_name_en' => 'Synthetic',
            'sender_name_ar' => 'تجريبي',
            'status' => 'active',
        ]);

        return $event;
    }
}
