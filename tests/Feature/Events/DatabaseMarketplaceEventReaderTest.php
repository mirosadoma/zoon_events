<?php

namespace Tests\Feature\Events;

use App\Models\User;
use App\Modules\Events\Application\Contracts\MarketplaceEventReader;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Database\Factories\Phase1\EventFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseMarketplaceEventReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reader_returns_only_the_owned_minimized_event_projection(): void
    {
        $organizer = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $creator = User::factory()->create();
        $event = EventFactory::new()->create([
            'tenant_id' => $organizer->id,
            'created_by_user_id' => $creator->id,
            'status' => 'published',
        ]);

        $reader = app(MarketplaceEventReader::class);
        $owned = $reader->readOwnedEvent($organizer->id, $event->id);

        self::assertTrue($owned->foundEvent());
        self::assertSame($organizer->id, $owned->event->tenantId);
        self::assertSame($event->slug, $owned->event->eventPublicId);
        self::assertSame($event->timezone, $owned->event->window->timezone);
        self::assertTrue($owned->event->creatorEligible);

        $hidden = $reader->readOwnedEvent($otherTenant->id, $event->id);
        self::assertFalse($hidden->foundEvent());
        self::assertSame('marketplace_event_not_found', $hidden->reason);
    }
}
