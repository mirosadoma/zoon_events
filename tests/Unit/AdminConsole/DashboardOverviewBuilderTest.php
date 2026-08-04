<?php

namespace Tests\Unit\AdminConsole;

use App\Modules\AdminConsole\Application\DashboardOverviewBuilder;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('admin-dashboard')]
final class DashboardOverviewBuilderTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_published_venue_markers_include_coords_and_exclude_draft_events(): void
    {
        $fixture = $this->createRegistrationFixture();
        $membership = TenantMembership::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'user_id' => $fixture['actor']->id,
            'status' => 'active',
            'created_by_user_id' => $fixture['actor']->id,
        ]);
        $context = new TenantContext($fixture['tenant'], $membership, $fixture['actor']);

        EventVenue::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'event_id' => $fixture['event']->id,
            'name_en' => 'Published Hall',
            'name_ar' => 'قاعة منشورة',
            'location_address' => 'Cairo',
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(2),
            'sort_order' => 0,
        ]);

        $draft = Event::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'slug' => 'draft-'.Str::lower((string) Str::ulid()),
            'name_en' => 'Draft Event',
            'name_ar' => 'مسودة',
            'tier' => 'public',
            'status' => 'draft',
            'timezone' => 'Africa/Cairo',
            'start_at' => '2027-02-10 12:00:00',
            'end_at' => '2027-02-10 18:00:00',
            'registration_opens_at' => '2027-01-01 00:00:00',
            'registration_closes_at' => '2027-02-10 11:00:00',
            'created_by_user_id' => $fixture['actor']->id,
        ]);

        EventVenue::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'event_id' => $draft->id,
            'name_en' => 'Draft Hall',
            'name_ar' => 'قاعة مسودة',
            'latitude' => 29.9,
            'longitude' => 31.2,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(2),
            'sort_order' => 0,
        ]);

        $overview = app(DashboardOverviewBuilder::class)->build($context);

        self::assertCount(1, $overview['published_venue_markers']);
        self::assertSame((string) $fixture['event']->id, $overview['published_venue_markers'][0]['event_id']);
        self::assertSame('Published Hall', $overview['published_venue_markers'][0]['venue_name']['en']);
        self::assertSame(30.0444, $overview['published_venue_markers'][0]['latitude']);
        self::assertNotEmpty($overview['published_venue_markers'][0]['color']);
        self::assertCount(14, $overview['registrations_by_day']);
        self::assertCount(14, $overview['checkins_by_day']);
        self::assertArrayHasKey('registered', $overview['funnel']);
        self::assertNotEmpty($overview['events_comparison']);
    }

    public function test_null_context_returns_empty_analytics_collections(): void
    {
        $overview = app(DashboardOverviewBuilder::class)->build(null);

        self::assertSame([], $overview['published_venue_markers']);
        self::assertSame([], $overview['events_comparison']);
        self::assertSame(0, $overview['funnel']['registered']);
    }
}
