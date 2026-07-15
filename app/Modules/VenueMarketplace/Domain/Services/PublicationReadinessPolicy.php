<?php

namespace App\Modules\VenueMarketplace\Domain\Services;

use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\Venue;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\VenueAsset;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\VenueAssetBinding;
use Throwable;

final readonly class PublicationReadinessPolicy
{
    public function __construct(private MarketplaceCapabilityRegistry $capabilities) {}

    public function assertReady(
        Venue $venue,
        VenueAsset $asset,
        ?VenueAssetBinding $binding,
        int $availableWindowCount,
        bool $hasFutureReservation = false,
    ): void {
        if ($venue->status !== 'active') {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_VENUE_NOT_PUBLISHABLE);
        }

        foreach ([
            $venue->name_en, $venue->name_ar, $venue->address_en, $venue->address_ar,
            $venue->country_code, $venue->city_code, $venue->timezone,
            $asset->name_en, $asset->name_ar, $asset->location_en, $asset->location_ar,
        ] as $required) {
            if (! is_string($required) || trim($required) === '') {
                $this->denyAsset();
            }
        }

        if ($asset->operational_status !== 'active' || $availableWindowCount < 1
            || $hasFutureReservation || $asset->price_minor < 0
            || ! preg_match('/^[A-Z]{3}$/', (string) $asset->currency)) {
            $this->denyAsset();
        }

        try {
            $definition = $this->capabilities->definition($asset->asset_type);
            $this->capabilities->assertCapabilities($asset->asset_type, $asset->capabilities ?? []);
        } catch (Throwable) {
            $this->denyAsset();
        }

        if ($definition['control_family'] === 'catalog_only') {
            if ($binding !== null && $binding->control_family !== 'catalog_only') {
                $this->denyAsset();
            }

            return;
        }

        if ($binding === null || $binding->status !== 'active'
            || $binding->control_family !== $definition['control_family']
            || ! is_string($binding->opaque_reference) || $binding->opaque_reference === '') {
            $this->denyAsset();
        }
    }

    private function denyAsset(): never
    {
        throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_NOT_PUBLISHABLE);
    }
}
