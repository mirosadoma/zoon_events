<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceCatalogPublication;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\VenueAsset;

final readonly class WithdrawVenueAssetPublicationAction
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
        bool $hasFutureReservation = false,
    ): MarketplaceCatalogPublication {
        return $this->transactions->run(
            function () use ($tenantId, $assetPublicId, $hasFutureReservation): MarketplaceCatalogPublication {
                $asset = VenueAsset::query()->forTenant((string) $tenantId)
                    ->where('public_id', $assetPublicId)->first();
                if ($asset === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_NOT_FOUND);
                }
                if ($hasFutureReservation) {
                    throw new MarketplaceDomainException(
                        Phase6Problem::MARKETPLACE_ASSET_NOT_PUBLISHABLE,
                        status: 409,
                    );
                }

                $publication = MarketplaceCatalogPublication::query()->forTenant((string) $tenantId)
                    ->where('venue_asset_id', $asset->id)->where('status', 'active')
                    ->lockForUpdate()->first();
                if ($publication === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_ASSET_NOT_FOUND);
                }

                $publication->forceFill(['status' => 'withdrawn', 'withdrawn_at' => now()])->save();

                return $publication;
            },
            fn (MarketplaceCatalogPublication $publication) => $this->audit->write(new MarketplaceAuditEvent(
                'venue_asset.publication_withdrawn',
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
