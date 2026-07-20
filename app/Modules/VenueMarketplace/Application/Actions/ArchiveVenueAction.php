<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\VenueLifecyclePolicy;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceCatalogPublication;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\Venue;

final readonly class ArchiveVenueAction
{
    public function __construct(
        private AuditedTransaction $transactions,
        private MarketplaceAuditWriter $audit,
        private VenueLifecyclePolicy $lifecycle,
    ) {}

    public function execute(
        int $tenantId,
        int $actorUserId,
        string $venuePublicId,
        string $correlationId,
        bool $hasActiveObligations = false,
    ): Venue {
        return $this->transactions->run(
            function () use ($tenantId, $actorUserId, $venuePublicId, $hasActiveObligations): Venue {
                $venue = Venue::query()->forTenant((string) $tenantId)
                    ->where('public_id', $venuePublicId)->lockForUpdate()->first();
                if ($venue === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_VENUE_NOT_FOUND);
                }
                if ($hasActiveObligations) {
                    throw new MarketplaceDomainException(
                        Phase6Problem::MARKETPLACE_VENUE_NOT_PUBLISHABLE,
                        status: 409,
                    );
                }

                $this->lifecycle->transition($venue->status, 'archived', $venue->toArray());
                MarketplaceCatalogPublication::query()->forTenant((string) $tenantId)
                    ->where('venue_id', $venue->id)->where('status', 'active')
                    ->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
                $venue->forceFill([
                    'status' => 'archived', 'archived_at' => now(),
                    'version' => $venue->version + 1, 'updated_by_user_id' => $actorUserId,
                ])->save();

                return $venue;
            },
            fn (Venue $venue) => $this->audit->write(new MarketplaceAuditEvent(
                'venue.archived', 'owner', 'succeeded', $correlationId, $venue->public_id,
                ['status' => 'archived', 'version' => $venue->version],
                ownerTenantId: $tenantId, actorUserId: $actorUserId,
            )),
        );
    }
}
