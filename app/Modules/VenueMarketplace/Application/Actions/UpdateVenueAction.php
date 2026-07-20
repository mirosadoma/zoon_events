<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\VenueLifecyclePolicy;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\Venue;
use Illuminate\Support\Arr;

final readonly class UpdateVenueAction
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
        int $expectedVersion,
        array $attributes,
        string $correlationId,
    ): Venue {
        return $this->transactions->run(
            function () use ($tenantId, $actorUserId, $venuePublicId, $expectedVersion, $attributes): Venue {
                $venue = Venue::query()->forTenant((string) $tenantId)
                    ->where('public_id', $venuePublicId)->lockForUpdate()->first();
                if ($venue === null) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_VENUE_NOT_FOUND);
                }
                if ((int) $venue->version !== $expectedVersion) {
                    throw new MarketplaceDomainException(
                        Phase6Problem::MARKETPLACE_VENUE_NOT_PUBLISHABLE,
                        status: 409,
                    );
                }
                if ($venue->isArchived()) {
                    throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_VENUE_NOT_PUBLISHABLE);
                }

                $changes = Arr::only($attributes, [
                    'name_en', 'name_ar', 'description_en', 'description_ar',
                    'address_en', 'address_ar', 'country_code', 'city_code', 'timezone',
                    'business_contact_name', 'business_contact_email',
                    'business_contact_phone', 'publish_contact',
                ]);
                $this->lifecycle->assertActivationReady(array_replace($venue->toArray(), $changes));
                $venue->forceFill([
                    ...$changes,
                    'version' => $venue->version + 1,
                    'updated_by_user_id' => $actorUserId,
                ])->save();

                return $venue;
            },
            fn (Venue $venue) => $this->audit->write(new MarketplaceAuditEvent(
                'venue.updated', 'owner', 'succeeded', $correlationId, $venue->public_id,
                ['before_version' => $expectedVersion, 'after_version' => $venue->version],
                ownerTenantId: $tenantId, actorUserId: $actorUserId,
            )),
        );
    }
}
