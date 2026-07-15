<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\VenueAssetBindingPolicy;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\Venue;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\VenueAsset;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\VenueAssetBinding;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final readonly class CreateVenueAssetAction
{
    public function __construct(
        private AuditedTransaction $transactions,
        private MarketplaceAuditWriter $audit,
        private VenueAssetBindingPolicy $policy,
    ) {}

    public function execute(
        int $tenantId,
        int $actorUserId,
        string $venuePublicId,
        array $attributes,
        array $binding,
        string $correlationId,
    ): VenueAsset {
        $this->policy->validate($attributes, $binding);

        return $this->transactions->run(
            function () use ($tenantId, $actorUserId, $venuePublicId, $attributes, $binding): VenueAsset {
                $venue = Venue::query()->forTenant((string) $tenantId)
                    ->where('public_id', $venuePublicId)->lockForUpdate()->first();
                if ($venue === null || $venue->isArchived()) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_VENUE_NOT_FOUND);
                }

                $asset = VenueAsset::query()->forceCreate([
                    ...Arr::only($attributes, [
                        'asset_type', 'name_en', 'name_ar', 'description_en', 'description_ar',
                        'location_en', 'location_ar', 'capabilities', 'capacity_per_minute',
                        'operational_status', 'pricing_model', 'price_minor', 'currency',
                    ]),
                    'tenant_id' => $tenantId, 'venue_id' => $venue->id,
                    'public_id' => (string) Str::ulid(), 'version' => 1,
                    'created_by_user_id' => $actorUserId, 'updated_by_user_id' => $actorUserId,
                ]);
                VenueAssetBinding::query()->forceCreate([
                    ...Arr::only($binding, [
                        'control_family', 'adapter_key', 'opaque_reference', 'binding_metadata', 'status',
                    ]),
                    'tenant_id' => $tenantId, 'venue_asset_id' => $asset->id,
                    'status' => $binding['status'] ?? 'active',
                ]);

                return $asset;
            },
            fn (VenueAsset $asset) => $this->audit->write(new MarketplaceAuditEvent(
                'venue_asset.created', 'owner', 'succeeded', $correlationId, $asset->public_id,
                ['asset_type' => $asset->asset_type, 'status' => $asset->operational_status, 'version' => 1],
                ownerTenantId: $tenantId, actorUserId: $actorUserId,
            )),
        );
    }
}
