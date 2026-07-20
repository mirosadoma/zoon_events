<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\PublicationReadinessPolicy;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\AssetAvailabilityWindow;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceCatalogPublication;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplacePublicationAvailabilityWindow;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplacePublicationCapability;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\VenueAsset;
use Illuminate\Support\Str;

final readonly class PublishVenueAssetAction
{
    public function __construct(
        private AuditedTransaction $transactions,
        private MarketplaceAuditWriter $audit,
        private PublicationReadinessPolicy $readiness,
    ) {}

    public function execute(
        int $tenantId,
        int $actorUserId,
        string $assetPublicId,
        string $correlationId,
    ): MarketplaceCatalogPublication {
        return $this->transactions->run(
            function () use ($tenantId, $assetPublicId): MarketplaceCatalogPublication {
                $asset = VenueAsset::query()->forTenant((string) $tenantId)
                    ->where('public_id', $assetPublicId)->lockForUpdate()->first();
                if ($asset === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_NOT_FOUND);
                }

                $venue = $asset->venue()->first();
                if ($venue === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_VENUE_NOT_FOUND);
                }
                $binding = $asset->binding()->first();
                $availability = AssetAvailabilityWindow::query()->forTenant((string) $tenantId)
                    ->where('venue_asset_id', $asset->id)->where('status', 'available')
                    ->orderBy('available_from')->orderBy('id')->get();
                $this->readiness->assertReady($venue, $asset, $binding, $availability->count());

                $previousVersion = (int) MarketplaceCatalogPublication::query()
                    ->forTenant((string) $tenantId)
                    ->where('venue_asset_id', $asset->id)
                    ->max('publication_version');
                MarketplaceCatalogPublication::query()->forTenant((string) $tenantId)
                    ->where('venue_asset_id', $asset->id)->where('status', 'active')
                    ->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);

                $publication = MarketplaceCatalogPublication::query()->forceCreate([
                    'tenant_id' => $tenantId,
                    'public_id' => (string) Str::ulid(),
                    'venue_id' => $venue->id,
                    'venue_asset_id' => $asset->id,
                    'venue_public_id' => $venue->public_id,
                    'asset_public_id' => $asset->public_id,
                    'publication_version' => $previousVersion + 1,
                    'venue_version' => $venue->version,
                    'asset_version' => $asset->version,
                    'venue_name_en' => $venue->name_en,
                    'venue_name_ar' => $venue->name_ar,
                    'venue_description_en' => $venue->description_en,
                    'venue_description_ar' => $venue->description_ar,
                    'asset_name_en' => $asset->name_en,
                    'asset_name_ar' => $asset->name_ar,
                    'asset_description_en' => $asset->description_en,
                    'asset_description_ar' => $asset->description_ar,
                    'address_en' => $venue->address_en,
                    'address_ar' => $venue->address_ar,
                    'country_code' => $venue->country_code,
                    'city_code' => $venue->city_code,
                    'timezone' => $venue->timezone,
                    'asset_type' => $asset->asset_type,
                    'location_en' => $asset->location_en,
                    'location_ar' => $asset->location_ar,
                    'capacity_per_minute' => $asset->capacity_per_minute,
                    'pricing_model' => $asset->pricing_model,
                    'price_minor' => $asset->price_minor,
                    'currency' => $asset->currency,
                    'availability_windows' => $availability->map(fn (AssetAvailabilityWindow $window): array => [
                        'starts_at' => $window->available_from->toISOString(),
                        'ends_at' => $window->available_until->toISOString(),
                    ])->values()->all(),
                    'public_contact' => $venue->publish_contact ? array_filter([
                        'name' => $venue->business_contact_name,
                        'email' => $venue->business_contact_email,
                        'phone' => $venue->business_contact_phone,
                    ], fn ($value) => $value !== null) : null,
                    'status' => 'active',
                    'published_at' => now(),
                ]);

                foreach (array_values(array_unique($asset->capabilities ?? [])) as $capability) {
                    MarketplacePublicationCapability::query()->forceCreate([
                        'tenant_id' => $tenantId,
                        'catalog_publication_id' => $publication->id,
                        'capability_code' => $capability,
                    ]);
                }
                foreach ($availability as $window) {
                    MarketplacePublicationAvailabilityWindow::query()->forceCreate([
                        'tenant_id' => $tenantId,
                        'catalog_publication_id' => $publication->id,
                        'available_from' => $window->available_from,
                        'available_until' => $window->available_until,
                    ]);
                }

                return $publication->load(['capabilities', 'availabilityWindows']);
            },
            fn (MarketplaceCatalogPublication $publication) => $this->audit->write(new MarketplaceAuditEvent(
                'venue_asset.published',
                'owner',
                'succeeded',
                $correlationId,
                $publication->public_id,
                ['publication_version' => $publication->publication_version, 'asset_public_id' => $assetPublicId],
                ownerTenantId: $tenantId,
                actorUserId: $actorUserId,
            )),
        );
    }
}
