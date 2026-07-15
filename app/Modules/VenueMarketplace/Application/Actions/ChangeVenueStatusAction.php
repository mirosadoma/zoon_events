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

final readonly class ChangeVenueStatusAction
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
        string $targetStatus,
        string $correlationId,
    ): Venue {
        return $this->transactions->run(
            function () use ($tenantId, $actorUserId, $venuePublicId, $targetStatus): Venue {
                $venue = Venue::query()->forTenant((string) $tenantId)
                    ->where('public_id', $venuePublicId)->lockForUpdate()->first();
                if ($venue === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_VENUE_NOT_FOUND);
                }

                $this->lifecycle->transition($venue->status, $targetStatus, $venue->toArray());
                $values = [
                    'status' => $targetStatus,
                    'version' => $venue->version + 1,
                    'updated_by_user_id' => $actorUserId,
                    'suspended_at' => $targetStatus === 'suspended' ? now() : null,
                ];
                if ($targetStatus === 'active' && $venue->activated_at === null) {
                    $values['activated_at'] = now();
                }
                $venue->forceFill($values)->save();

                if ($targetStatus === 'suspended') {
                    MarketplaceCatalogPublication::query()->forTenant((string) $tenantId)
                        ->where('venue_id', $venue->id)->where('status', 'active')
                        ->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
                }

                return $venue;
            },
            fn (Venue $venue) => $this->audit->write(new MarketplaceAuditEvent(
                'venue.status_changed', 'owner', 'succeeded', $correlationId, $venue->public_id,
                ['status' => $venue->status, 'version' => $venue->version],
                ownerTenantId: $tenantId, actorUserId: $actorUserId,
            )),
        );
    }
}
