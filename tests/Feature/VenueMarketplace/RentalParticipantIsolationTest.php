<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\VenueMarketplace\Application\Queries\GetParticipantRentalQuery;
use App\Modules\VenueMarketplace\Application\Queries\ListParticipantRentalsQuery;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class RentalParticipantIsolationTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_only_owner_and_organizer_can_list_or_read_shared_rental(): void
    {
        $organizer = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        $event = $this->createMarketplaceEvent($organizer['tenant'], $organizer['user']);
        $owner = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $inventory = $this->createPublishedMarketplaceInventory($owner, 'participant');
        $publicationId = $inventory['assets'][3]->publications()->where('status', 'active')->value('public_id');
        $rental = $this->createSubmittedMarketplaceRental($organizer, $event, [$publicationId], 'participant');
        $unrelated = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        DB::flushQueryLog();
        DB::enableQueryLog();

        self::assertCount(1, app(ListParticipantRentalsQuery::class)->execute((int) $owner['tenant']->id)->items);
        self::assertCount(1, app(ListParticipantRentalsQuery::class)->execute((int) $organizer['tenant']->id)->items);
        self::assertSame($rental->public_id, app(GetParticipantRentalQuery::class)
            ->execute((int) $owner['tenant']->id, $rental->public_id)->public_id);
        $queries = strtolower(json_encode(DB::getQueryLog(), JSON_THROW_ON_ERROR));
        self::assertStringContainsString('organizer_tenant_id', $queries);
        self::assertStringContainsString('tenant_id', $queries);

        try {
            app(GetParticipantRentalQuery::class)->execute((int) $unrelated['tenant']->id, $rental->public_id);
            self::fail('Expected unrelated participant denial.');
        } catch (MarketplaceDomainException $exception) {
            self::assertSame(Phase6Problem::MARKETPLACE_RENTAL_NOT_FOUND, $exception->reasonCode);
        }
    }
}
