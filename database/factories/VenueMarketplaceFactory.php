<?php

namespace Database\Factories;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\VenueMarketplace\Application\Actions\ChangeVenueStatusAction;
use App\Modules\VenueMarketplace\Application\Actions\CreateVenueAction;
use App\Modules\VenueMarketplace\Application\Actions\CreateVenueAssetAction;
use App\Modules\VenueMarketplace\Application\Actions\PublishVenueAssetAction;
use App\Modules\VenueMarketplace\Application\Actions\ReplaceAssetAvailabilityAction;
use App\Modules\VenueMarketplace\Application\Actions\SubmitRentalRequestAction;
use App\Modules\VenueMarketplace\Application\Queries\MarketplaceCatalogReader;
use App\Modules\VenueMarketplace\Domain\Services\MarketplaceQuoteService;
use App\Modules\VenueMarketplace\Domain\ValueObjects\RentalWindow;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\Venue;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\VenueAsset;
use Illuminate\Support\Str;

final readonly class VenueMarketplaceFactory
{
    private const ASSETS = [
        'turnstile' => ['acs.configure', 'acs'],
        'security_gate' => ['acs.configure', 'acs'],
        'camera' => [null, 'catalog_only'],
        'kiosk' => ['kiosk.manage', 'kiosk'],
        'printer' => ['badge.print', 'printer'],
        'scanner' => ['checkin.scan.submit', 'scanner'],
        'access_lane' => ['acs.configure', 'acs'],
        'access_zone' => ['acs.configure', 'acs'],
    ];

    public function __construct(
        private CreateVenueAction $createVenue,
        private ChangeVenueStatusAction $changeVenueStatus,
        private CreateVenueAssetAction $createAsset,
        private ReplaceAssetAvailabilityAction $replaceAvailability,
        private PublishVenueAssetAction $publishAsset,
        private MarketplaceCatalogReader $catalog,
        private MarketplaceQuoteService $quotes,
        private SubmitRentalRequestAction $submitRental,
    ) {}

    /**
     * @return array{venue:Venue,assets:list<VenueAsset>}
     */
    public function createPublishedInventory(int $tenantId, int $actorUserId, string $key = 'fixture'): array
    {
        $venue = $this->createVenue->execute($tenantId, $actorUserId, [
            'name_en' => 'Marketplace Fixture Venue',
            'name_ar' => 'موقع اختبار السوق',
            'description_en' => 'Deterministic private venue fixture',
            'description_ar' => 'بيانات موقع خاصة للاختبار',
            'address_en' => 'Fixture Road',
            'address_ar' => 'طريق الاختبار',
            'country_code' => 'SA',
            'city_code' => 'riyadh',
            'timezone' => 'Asia/Riyadh',
            'business_contact_email' => 'private-fixture@example.test',
            'publish_contact' => false,
        ], "{$key}-venue-create");
        $venue = $this->changeVenueStatus->execute(
            $tenantId,
            $actorUserId,
            $venue->public_id,
            'active',
            "{$key}-venue-activate",
        );

        $assets = [];
        foreach (self::ASSETS as $type => [$capability, $controlFamily]) {
            $asset = $this->createAsset->execute(
                $tenantId,
                $actorUserId,
                $venue->public_id,
                [
                    'asset_type' => $type,
                    'name_en' => str_replace('_', ' ', ucfirst($type)),
                    'name_ar' => 'أصل '.$type,
                    'description_en' => "Published {$type} fixture",
                    'description_ar' => 'أصل منشور للاختبار',
                    'location_en' => 'Hall A',
                    'location_ar' => 'القاعة أ',
                    'capabilities' => $capability === null ? [] : [$capability],
                    'capacity_per_minute' => $type === 'camera' ? null : 10,
                    'operational_status' => 'active',
                    'pricing_model' => 'per_hour',
                    'price_minor' => 1000,
                    'currency' => 'SAR',
                ],
                array_filter([
                    'control_family' => $controlFamily,
                    'adapter_key' => $controlFamily === 'catalog_only' ? null : 'fake',
                    'opaque_reference' => $controlFamily === 'catalog_only' ? null : "opaque:{$type}:{$key}",
                    'status' => 'active',
                ], static fn (mixed $value): bool => $value !== null),
                "{$key}-{$type}-create",
            );
            $this->replaceAvailability->execute($tenantId, $actorUserId, $asset->public_id, 1, [[
                'local_from' => '2027-01-10 09:00:00',
                'local_until' => '2027-01-10 18:00:00',
                'status' => 'available',
            ]], "{$key}-{$type}-availability");
            $this->publishAsset->execute(
                $tenantId,
                $actorUserId,
                $asset->public_id,
                "{$key}-{$type}-publish",
            );
            $assets[] = $asset->fresh();
        }

        return ['venue' => $venue->fresh(), 'assets' => $assets];
    }

    /** @param list<string> $publicationPublicIds */
    public function createSubmittedRental(
        int $organizerTenantId,
        int $actorUserId,
        int $eventId,
        array $publicationPublicIds,
        string $requestedStartAt = '2027-01-10T06:00:00Z',
        string $requestedEndAt = '2027-01-10T08:00:00Z',
        string $key = 'fixture-rental',
    ): RentalRequest {
        $publications = $this->catalog->getAvailable(
            $publicationPublicIds,
            $requestedStartAt,
            $requestedEndAt,
        );
        $quote = $this->quotes->calculate(
            $publications,
            new RentalWindow(
                new \DateTimeImmutable($requestedStartAt),
                new \DateTimeImmutable($requestedEndAt),
            ),
        );

        return $this->submitRental->execute(
            $organizerTenantId,
            $actorUserId,
            $eventId,
            $publicationPublicIds,
            $requestedStartAt,
            $requestedEndAt,
            $quote['quote_digest'],
            $quote['quote_version'],
            "{$key}-idempotency-key",
            "{$key}-correlation",
        );
    }

    public function createOrganizerEvent(
        int $organizerTenantId,
        int $actorUserId,
        string $key = 'fixture-event',
    ): Event {
        return Event::query()->create([
            'tenant_id' => $organizerTenantId,
            'slug' => 'marketplace-'.Str::slug($key).'-'.Str::lower((string) Str::ulid()),
            'name_en' => 'Marketplace Rental Event',
            'name_ar' => 'فعالية إيجار السوق',
            'tier' => 'public',
            'status' => 'published',
            'timezone' => 'Asia/Riyadh',
            'start_at' => '2027-01-10 06:00:00',
            'end_at' => '2027-01-10 15:00:00',
            'registration_opens_at' => '2026-01-01 00:00:00',
            'registration_closes_at' => '2027-01-10 05:00:00',
            'capacity' => 100,
            'created_by_user_id' => $actorUserId,
            'published_by_user_id' => $actorUserId,
            'published_at' => now(),
        ]);
    }
}
