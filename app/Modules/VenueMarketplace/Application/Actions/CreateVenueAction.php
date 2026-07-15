<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Tenancy\Application\Contracts\OrganizationEligibility;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditEvent;
use App\Modules\VenueMarketplace\Application\Audit\MarketplaceAuditWriter;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Domain\Services\VenueLifecyclePolicy;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\Venue;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final readonly class CreateVenueAction
{
    public function __construct(
        private OrganizationEligibility $eligibility,
        private AuditedTransaction $transactions,
        private MarketplaceAuditWriter $audit,
        private VenueLifecyclePolicy $lifecycle,
    ) {}

    public function execute(int $tenantId, int $actorUserId, array $attributes, string $correlationId): Venue
    {
        if (! $this->eligibility->check($tenantId, OrganizationEligibility::OWN_VENUES)->eligible) {
            throw new MarketplaceDomainException(Phase6Problem::ORGANIZATION_TYPE_NOT_ELIGIBLE);
        }

        return $this->transactions->run(
            function () use ($tenantId, $actorUserId, $attributes): Venue {
                $this->lifecycle->assertActivationReady($attributes);

                return Venue::query()->forceCreate([
                    ...Arr::only($attributes, [
                        'name_en', 'name_ar', 'description_en', 'description_ar',
                        'address_en', 'address_ar', 'country_code', 'city_code', 'timezone',
                        'business_contact_name', 'business_contact_email',
                        'business_contact_phone', 'publish_contact',
                    ]),
                    'tenant_id' => $tenantId,
                    'public_id' => (string) Str::ulid(),
                    'status' => 'draft',
                    'version' => 1,
                    'created_by_user_id' => $actorUserId,
                    'updated_by_user_id' => $actorUserId,
                ]);
            },
            fn (Venue $venue) => $this->audit->write(new MarketplaceAuditEvent(
                'venue.created', 'owner', 'succeeded', $correlationId, $venue->public_id,
                ['status' => 'draft', 'version' => 1],
                ownerTenantId: $tenantId, actorUserId: $actorUserId,
            )),
        );
    }
}
