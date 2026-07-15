<?php

namespace App\Modules\VenueMarketplace\Application\Authorization;

use App\Modules\Authorization\Application\Contracts\DelegatedControlDecision;
use App\Modules\Authorization\Application\Contracts\DelegatedControlGuard;
use App\Modules\Authorization\Application\Contracts\DelegatedControlRequest;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\ControlDelegation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\DelegatedAssetResource;
use Illuminate\Database\QueryException;

final class DatabaseDelegatedControlGuard implements DelegatedControlGuard
{
    public function decide(DelegatedControlRequest $request): DelegatedControlDecision
    {
        if (! $request->existingPermissionAllowed) {
            return new DelegatedControlDecision(false, Phase6Problem::MARKETPLACE_PERMISSION_DENIED);
        }

        if ($request->delegationPublicId === null) {
            return new DelegatedControlDecision(true);
        }

        try {
            $delegation = ControlDelegation::query()
                ->withoutGlobalScopes()
                ->where('organizer_tenant_id', $request->organizerTenantId)
                ->where('public_id', $request->delegationPublicId)
                ->lockForUpdate()
                ->first();
        } catch (QueryException) {
            return $this->deny(Phase6Problem::MARKETPLACE_DELEGATION_NOT_FOUND);
        }

        if ($delegation === null) {
            return $this->deny(Phase6Problem::MARKETPLACE_DELEGATION_NOT_FOUND);
        }

        if ($delegation->revoked_at !== null || $delegation->status === 'revoked') {
            return $this->deny(Phase6Problem::MARKETPLACE_DELEGATION_REVOKED);
        }

        if ($request->now < $delegation->starts_at) {
            return $this->deny(Phase6Problem::MARKETPLACE_DELEGATION_NOT_STARTED);
        }

        if ($request->now >= $delegation->ends_at) {
            return $this->deny(Phase6Problem::MARKETPLACE_DELEGATION_EXPIRED);
        }

        if (! in_array($delegation->status, ['active', 'degraded'], true)) {
            return $this->deny(Phase6Problem::MARKETPLACE_DELEGATION_NOT_STARTED);
        }

        if ((int) $delegation->event_id !== $request->eventId) {
            return $this->deny(Phase6Problem::MARKETPLACE_EVENT_SCOPE_DENIED);
        }

        $resource = DelegatedAssetResource::query()
            ->withoutGlobalScopes()
            ->where('control_delegation_id', $delegation->id)
            ->where('resource_module', $request->resourceModule)
            ->where('resource_type', $request->resourceType)
            ->where('resource_public_reference', $request->resourcePublicReference)
            ->where('provisioning_status', 'provisioned')
            ->first();

        if ($resource === null) {
            return $this->deny(Phase6Problem::MARKETPLACE_ASSET_SCOPE_DENIED);
        }

        $grantedCapabilities = $resource->granted_capabilities ?? [];
        if (! in_array($request->requestedCapability, $grantedCapabilities, true)) {
            return $this->deny(Phase6Problem::MARKETPLACE_CAPABILITY_DENIED);
        }

        return new DelegatedControlDecision(
            allowed: true,
            rentalPublicId: $delegation->rental?->public_id,
            delegationPublicId: $delegation->public_id,
            startsAt: $delegation->starts_at,
            endsAt: $delegation->ends_at,
        );
    }

    private function deny(string $reason): DelegatedControlDecision
    {
        return new DelegatedControlDecision(false, $reason);
    }
}
