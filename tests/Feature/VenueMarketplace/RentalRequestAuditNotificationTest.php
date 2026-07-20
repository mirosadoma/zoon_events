<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class RentalRequestAuditNotificationTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_request_writes_correlated_participant_audits_and_queues_after_commit(): void
    {
        Queue::fake();
        $organizer = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        $event = $this->createMarketplaceEvent($organizer['tenant'], $organizer['user']);
        $owner = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $inventory = $this->createPublishedMarketplaceInventory($owner, 'audit-request');
        $publicationId = $inventory['assets'][3]->publications()->where('status', 'active')->value('public_id');

        $rental = $this->createSubmittedMarketplaceRental($organizer, $event, [$publicationId], 'audit-request');
        $logs = AuditLog::query()->where('action', 'rental.requested')->get();
        $serialized = json_encode($logs->pluck('metadata')->all(), JSON_THROW_ON_ERROR);

        self::assertCount(2, $logs);
        self::assertCount(1, $logs->pluck('metadata.correlation_id')->unique());
        self::assertStringNotContainsString('private-fixture@example.test', $serialized);
        self::assertStringNotContainsString('opaque:', $serialized);
        Queue::assertPushed(CallQueuedListener::class);
        self::assertSame(1, $rental->assets->count());
    }
}
