<?php

namespace Tests\Feature\VenueMarketplace;

use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Queries\MarketplaceCatalogReader;
use App\Modules\VenueMarketplace\Application\Services\RentalEventSnapshotResolver;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class MarketplaceQuoteValidationTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_foreign_events_and_opaque_publication_ids_are_non_enumerating(): void
    {
        $organizer = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        $other = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        $event = $this->createMarketplaceEvent($other['tenant'], $other['user']);

        try {
            app(RentalEventSnapshotResolver::class)->resolve((int) $organizer['tenant']->id, (int) $event->id);
            self::fail('Expected foreign event denial.');
        } catch (MarketplaceDomainException $exception) {
            self::assertSame(Phase6Problem::MARKETPLACE_EVENT_NOT_FOUND, $exception->reasonCode);
        }

        app(TenantContextStore::class)->clear();
        $this->expectException(MarketplaceDomainException::class);
        app(MarketplaceCatalogReader::class)->get('01ARZ3NDEKTSV4RRFFQ69G5FAV');
    }
}
