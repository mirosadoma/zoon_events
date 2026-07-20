<?php

namespace Tests\Feature\VenueMarketplace;

use App\Exceptions\FoundationException;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Actions\SubmitRentalRequestAction;
use App\Modules\VenueMarketplace\Application\Queries\MarketplaceCatalogReader;
use App\Modules\VenueMarketplace\Domain\Services\MarketplaceQuoteService;
use App\Modules\VenueMarketplace\Domain\ValueObjects\RentalWindow;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalAsset;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CreatesMarketplaceFixture;
use Tests\TestCase;

final class SubmitRentalRequestTest extends TestCase
{
    use CreatesMarketplaceFixture;
    use RefreshDatabase;

    public function test_submission_is_immutable_all_or_nothing_and_idempotent(): void
    {
        $this->freezeMarketplaceClock();
        $organizer = $this->createTenantMember(tenantAttributes: ['organization_type' => 'organizer']);
        $event = $this->createMarketplaceEvent($organizer['tenant'], $organizer['user']);
        $owner = $this->createTenantMember(tenantAttributes: ['organization_type' => 'venue_owner']);
        $inventory = $this->createPublishedMarketplaceInventory($owner, 'submit-rental');
        app(TenantContextStore::class)->clear();
        $publicationPublicId = $inventory['assets'][3]->publications()
            ->where('status', 'active')->value('public_id');
        $publication = app(MarketplaceCatalogReader::class)->getAvailable(
            [$publicationPublicId],
            '2027-01-10T06:00:00Z',
            '2027-01-10T08:00:00Z',
        );
        $quote = app(MarketplaceQuoteService::class)->calculate(
            $publication,
            new RentalWindow(
                new \DateTimeImmutable('2027-01-10T06:00:00Z'),
                new \DateTimeImmutable('2027-01-10T08:00:00Z'),
            ),
        );
        $arguments = [
            (int) $organizer['tenant']->id,
            (int) $organizer['user']->id,
            (int) $event->id,
            [$publication->first()->public_id],
            '2027-01-10T06:00:00Z',
            '2027-01-10T08:00:00Z',
            $quote['quote_digest'],
            $quote['quote_version'],
            'rental-idempotency-key-0001',
            'rental-correlation-0001',
        ];

        $first = app(SubmitRentalRequestAction::class)->execute(...$arguments);
        $replay = app(SubmitRentalRequestAction::class)->execute(...$arguments);

        self::assertSame($first->id, $replay->id);
        self::assertSame(1, RentalRequest::query()->withoutGlobalScopes()->count());
        self::assertSame(1, RentalAsset::query()->withoutGlobalScopes()->count());
        self::assertSame($quote['total_minor'], $first->total_minor);
        self::assertSame($publication->first()->asset_name_en, $first->assets->first()->name_en);

        $publication->first()->forceFill(['asset_name_en' => 'Later publication edit'])->save();
        self::assertNotSame('Later publication edit', $first->assets->first()->fresh()->name_en);

        $mismatch = $arguments;
        $mismatch[5] = '2027-01-10T09:00:00Z';
        try {
            app(SubmitRentalRequestAction::class)->execute(...$mismatch);
            self::fail('Expected idempotency payload mismatch.');
        } catch (FoundationException $exception) {
            self::assertSame('idempotency_conflict', $exception->problemCode);
        }

        Cache::forget('marketplace:quote:'.$quote['quote_digest']);
        $expired = $arguments;
        $expired[8] = 'rental-idempotency-key-expired';
        try {
            app(SubmitRentalRequestAction::class)->execute(...$expired);
            self::fail('Expected expired quote rejection.');
        } catch (\App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException $exception) {
            self::assertSame('marketplace_quote_changed', $exception->reasonCode);
        }
    }
}
