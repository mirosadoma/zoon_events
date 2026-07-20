<?php

namespace App\Modules\VenueMarketplace\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\VenueMarketplace\Application\Queries\GetParticipantDelegationQuery;
use App\Modules\VenueMarketplace\Http\Resources\ParticipantDelegationResource;
use Illuminate\Http\Request;

final class ParticipantDelegationController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $tenants,
    ) {}

    public function show(Request $request, string $rental_public_id, GetParticipantDelegationQuery $query)
    {
        $tenantId = (int) $this->tenants->current()->tenant->id;
        $delegation = $query->execute($tenantId, $rental_public_id);

        return $this->success((new ParticipantDelegationResource($delegation))->resolve($request));
    }
}
