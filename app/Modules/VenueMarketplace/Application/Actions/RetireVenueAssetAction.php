<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceCatalogPublication;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\VenueAsset;

final readonly class RetireVenueAssetAction
{
    public function __construct(
        private AuditedTransaction $transactions,
        private MarketplaceAuditWriter $audit,
    ) {}

    public function execute(
        int $tenantId,
        int $actorUserId,
        string $assetPublicId,
        string $correlationId,
        bool $hasActiveObligations = false,
    ): VenueAsset {
        return $this->transactions->run(
            function () use ($tenantId, $actorUserId, $assetPublicId, $hasActiveObligations): VenueAsset {
                $asset = VenueAsset::query()->forTenant((string) $tenantId)
                    ->where('public_id', $assetPublicId)->lockForUpdate()->first();
                if ($asset === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_NOT_FOUND);
                }
                if ($asset->isRetired() || $hasActiveObligations) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_UNAVAILABLE);
                }

                MarketplaceCatalogPublication::query()->forTenant((string) $tenantId)
                    ->where('venue_asset_id', $asset->id)->where('status', 'active')
                    ->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
                $asset->forceFill([
                    'operational_status' => 'retired', 'retired_at' => now(),
                    'version' => $asset->version + 1, 'updated_by_user_id' => $actorUserId,
                ])->save();

                return $asset;
            },
            fn (VenueAsset $asset) => $this->audit->write(new MarketplaceAuditEvent(
                'venue_asset.retired', 'owner', 'succeeded', $correlationId, $asset->public_id,
                ['status' => 'retired', 'version' => $asset->version],
                ownerTenantId: $tenantId, actorUserId: $actorUserId,
            )),
        );
    }
}
