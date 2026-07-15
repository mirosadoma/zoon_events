<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\AccessControl\Application\Contracts\DelegatedAcsAssetPort;
use App\Modules\AccessControl\Application\Contracts\DelegatedAcsProvisionRequest;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterAssetPort;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterProvisionRequest;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskAssetPort;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskProvisionRequest;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerAssetPort;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerProvisionRequest;
use App\Modules\VenueMarketplace\Application\Services\CatalogOnlyCameraProvisioner;
use App\Modules\VenueMarketplace\Application\Services\DelegatedAssetProvisionerRegistry;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\ControlDelegation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\DelegatedAssetResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ProvisionDelegatedAssetsAction
{
    public function __construct(
        private DelegatedAssetProvisionerRegistry $registry,
    ) {}

    public function execute(
        int $ownerTenantId,
        string $delegationPublicId,
        string $correlationId,
    ): ControlDelegation {
        return DB::transaction(function () use ($ownerTenantId, $delegationPublicId, $correlationId): ControlDelegation {
            $delegation = ControlDelegation::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $ownerTenantId)
                ->where('public_id', $delegationPublicId)
                ->lockForUpdate()
                ->first();

            if ($delegation === null) {
                throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_DELEGATION_NOT_FOUND);
            }

            $now = now()->toDateTimeImmutable();

            if ($delegation->revoked_at !== null) {
                throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_DELEGATION_REVOKED);
            }

            if ($now >= $delegation->ends_at) {
                throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_DELEGATION_EXPIRED);
            }

            if (in_array($delegation->status, ['revoked', 'expired', 'completed'], true)) {
                throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_DELEGATION_REVOKED);
            }

            $resources = DelegatedAssetResource::query()
                ->withoutGlobalScopes()
                ->where('control_delegation_id', $delegation->id)
                ->where('tenant_id', $ownerTenantId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $hasDegraded = false;

            foreach ($resources as $resource) {
                if ($resource->provisioning_status === 'not_applicable') {
                    continue;
                }

                if ($resource->provisioning_status === 'provisioned' && $resource->provisioned_at !== null) {
                    continue;
                }

                $port = $this->registry->resolve($resource->resource_module);
                $result = $this->callProvision($port, $resource, $delegation, $correlationId);

                $resource->forceFill([
                    'provisioning_status' => $result['status'],
                    'resource_public_reference' => $result['resourcePublicReference'],
                    'provisioned_at' => $result['status'] === 'provisioned' ? now() : null,
                    'failure_reason_code' => $result['reason'],
                ])->save();

                if ($result['status'] === 'degraded') {
                    $hasDegraded = true;
                }
            }

            $newStatus = $hasDegraded ? 'degraded' : 'active';
            $delegation->forceFill([
                'status' => $newStatus,
                'degraded_reason_code' => $hasDegraded ? 'partial_adapter_failure' : null,
                'provision_attempts' => ((int) $delegation->provision_attempts) + 1,
                'last_provision_attempt_at' => now(),
                'version' => ((int) $delegation->version) + 1,
            ])->save();

            return $delegation->fresh(['resources']);
        });
    }

    private function callProvision(
        DelegatedAcsAssetPort|DelegatedKioskAssetPort|DelegatedPrinterAssetPort|DelegatedScannerAssetPort|CatalogOnlyCameraProvisioner $port,
        DelegatedAssetResource $resource,
        ControlDelegation $delegation,
        string $correlationId,
    ): array {
        $idempotencyKey = $resource->idempotency_key_hash . ':provision';

        try {
            if ($port instanceof CatalogOnlyCameraProvisioner) {
                return $port->provision();
            }

            $assetPublicId = (string) Str::ulid();

            if ($port instanceof DelegatedAcsAssetPort) {
                $result = $port->provision(new DelegatedAcsProvisionRequest(
                    organizerTenantId: (string) $delegation->organizer_tenant_id,
                    eventPublicId: (string) $delegation->event_id,
                    delegationPublicId: $delegation->public_id,
                    assetPublicId: $assetPublicId,
                    assetType: $resource->resource_type,
                    capabilities: $resource->granted_capabilities ?? [],
                    startsAt: $delegation->starts_at,
                    endsAt: $delegation->ends_at,
                    correlationId: $correlationId,
                    idempotencyKey: $idempotencyKey,
                ));
            } elseif ($port instanceof DelegatedKioskAssetPort) {
                $result = $port->provision(new DelegatedKioskProvisionRequest(
                    organizerTenantId: (string) $delegation->organizer_tenant_id,
                    eventPublicId: (string) $delegation->event_id,
                    delegationPublicId: $delegation->public_id,
                    assetPublicId: $assetPublicId,
                    capabilities: $resource->granted_capabilities ?? [],
                    startsAt: $delegation->starts_at,
                    endsAt: $delegation->ends_at,
                    correlationId: $correlationId,
                    idempotencyKey: $idempotencyKey,
                ));
            } elseif ($port instanceof DelegatedPrinterAssetPort) {
                $result = $port->provision(new DelegatedPrinterProvisionRequest(
                    organizerTenantId: (string) $delegation->organizer_tenant_id,
                    eventPublicId: (string) $delegation->event_id,
                    delegationPublicId: $delegation->public_id,
                    assetPublicId: $assetPublicId,
                    capabilities: $resource->granted_capabilities ?? [],
                    startsAt: $delegation->starts_at,
                    endsAt: $delegation->ends_at,
                    correlationId: $correlationId,
                    idempotencyKey: $idempotencyKey,
                ));
            } else {
                $result = $port->provision(new DelegatedScannerProvisionRequest(
                    organizerTenantId: (string) $delegation->organizer_tenant_id,
                    eventPublicId: (string) $delegation->event_id,
                    delegationPublicId: $delegation->public_id,
                    assetPublicId: $assetPublicId,
                    capabilities: $resource->granted_capabilities ?? [],
                    startsAt: $delegation->starts_at,
                    endsAt: $delegation->ends_at,
                    correlationId: $correlationId,
                    idempotencyKey: $idempotencyKey,
                ));
            }

            return [
                'status' => $result->status,
                'resourceType' => $result->resourceType,
                'resourcePublicReference' => $result->resourcePublicReference,
                'acceptedCapabilities' => $result->acceptedCapabilities,
                'reason' => $result->reason,
            ];
        } catch (\Throwable) {
            return [
                'status' => 'degraded',
                'resourceType' => $resource->resource_type,
                'resourcePublicReference' => null,
                'acceptedCapabilities' => [],
                'reason' => 'adapter_exception',
            ];
        }
    }
}
