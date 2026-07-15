<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\VenueAssetBindingPolicy;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\VenueAsset;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\VenueAssetBinding;
use Illuminate\Support\Arr;

final readonly class UpdateVenueAssetAction
{
    public function __construct(
        private AuditedTransaction $transactions,
        private MarketplaceAuditWriter $audit,
        private VenueAssetBindingPolicy $policy,
    ) {}

    public function execute(
        int $tenantId,
        int $actorUserId,
        string $assetPublicId,
        int $expectedVersion,
        array $attributes,
        array $binding,
        string $correlationId,
    ): VenueAsset {
        $this->policy->validate($attributes, $binding);

        return $this->transactions->run(
            function () use ($tenantId, $actorUserId, $assetPublicId, $expectedVersion, $attributes, $binding): VenueAsset {
                $asset = VenueAsset::query()->forTenant((string) $tenantId)
                    ->where('public_id', $assetPublicId)->lockForUpdate()->first();
                if ($asset === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_NOT_FOUND);
                }
                if ((int) $asset->version !== $expectedVersion) {
                    throw new MarketplaceDomainException(
                        Phase6Problem::MARKETPLACE_ASSET_NOT_PUBLISHABLE,
                        status: 409,
                    );
                }
                if ($asset->isRetired()) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_NOT_PUBLISHABLE);
                }

                $asset->forceFill([
                    ...Arr::only($attributes, [
                        'asset_type', 'name_en', 'name_ar', 'description_en', 'description_ar',
                        'location_en', 'location_ar', 'capabilities', 'capacity_per_minute',
                        'operational_status', 'pricing_model', 'price_minor', 'currency',
                    ]),
                    'version' => $asset->version + 1,
                    'updated_by_user_id' => $actorUserId,
                ])->save();
                $bindingValues = Arr::only($binding, [
                    'control_family', 'adapter_key', 'opaque_reference', 'binding_metadata', 'status',
                ]);
                $storedBinding = VenueAssetBinding::query()->forTenant((string) $tenantId)
                    ->where('venue_asset_id', $asset->id)->first();
                if ($storedBinding === null) {
                    VenueAssetBinding::query()->forceCreate([
                        ...$bindingValues, 'tenant_id' => $tenantId, 'venue_asset_id' => $asset->id,
                    ]);
                } else {
                    $storedBinding->forceFill($bindingValues)->save();
                }

                return $asset;
            },
            fn (VenueAsset $asset) => $this->audit->write(new MarketplaceAuditEvent(
                'venue_asset.updated', 'owner', 'succeeded', $correlationId, $asset->public_id,
                ['before_version' => $expectedVersion, 'after_version' => $asset->version],
                ownerTenantId: $tenantId, actorUserId: $actorUserId,
            )),
        );
    }
}
