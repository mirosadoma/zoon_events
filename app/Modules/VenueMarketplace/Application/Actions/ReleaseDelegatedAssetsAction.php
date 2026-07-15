<?php

namespace App\Modules\VenueMarketplace\Application\Actions;

use App\Modules\AccessControl\Application\Contracts\DelegatedAcsAssetPort;
use App\Modules\AccessControl\Application\Contracts\DelegatedAcsReleaseRequest;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterAssetPort;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterReleaseRequest;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskAssetPort;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskReleaseRequest;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerAssetPort;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerReleaseRequest;
use App\Modules\VenueMarketplace\Application\Services\CatalogOnlyCameraProvisioner;
use App\Modules\VenueMarketplace\Application\Services\DelegatedAssetProvisionerRegistry;
use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\ControlDelegation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\DelegatedAssetResource;
use Illuminate\Support\Facades\DB;

final readonly class ReleaseDelegatedAssetsAction
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

            $resources = DelegatedAssetResource::query()
                ->withoutGlobalScopes()
                ->where('control_delegation_id', $delegation->id)
                ->where('tenant_id', $ownerTenantId)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            foreach ($resources as $resource) {
                if ($resource->released_at !== null) {
                    continue;
                }

                if (in_array($resource->provisioning_status, ['not_applicable', 'pending'], true)) {
                    $resource->forceFill([
                        'provisioning_status' => $resource->provisioning_status === 'pending' ? 'released' : $resource->provisioning_status,
                        'released_at' => now(),
                    ])->save();

                    continue;
                }

                if ($resource->resource_public_reference === null) {
                    $resource->forceFill([
                        'provisioning_status' => 'released',
                        'released_at' => now(),
                    ])->save();

                    continue;
                }

                $port = $this->registry->resolve($resource->resource_module);
                $this->callRelease($port, $resource, $delegation, $correlationId);

                $resource->forceFill([
                    'provisioning_status' => 'released',
                    'released_at' => now(),
                ])->save();
            }

            return $delegation->fresh(['resources']);
        });
    }

    private function callRelease(
        DelegatedAcsAssetPort|DelegatedKioskAssetPort|DelegatedPrinterAssetPort|DelegatedScannerAssetPort|CatalogOnlyCameraProvisioner $port,
        DelegatedAssetResource $resource,
        ControlDelegation $delegation,
        string $correlationId,
    ): void {
        $idempotencyKey = $resource->idempotency_key_hash . ':release';

        try {
            if ($port instanceof CatalogOnlyCameraProvisioner) {
                return;
            }

            if ($port instanceof DelegatedAcsAssetPort) {
                $port->release(new DelegatedAcsReleaseRequest(
                    organizerTenantId: (string) $delegation->organizer_tenant_id,
                    eventPublicId: (string) $delegation->event_id,
                    delegationPublicId: $delegation->public_id,
                    assetPublicId: $resource->resource_public_reference,
                    resourcePublicReference: $resource->resource_public_reference,
                    correlationId: $correlationId,
                    idempotencyKey: $idempotencyKey,
                ));
            } elseif ($port instanceof DelegatedKioskAssetPort) {
                $port->release(new DelegatedKioskReleaseRequest(
                    organizerTenantId: (string) $delegation->organizer_tenant_id,
                    eventPublicId: (string) $delegation->event_id,
                    delegationPublicId: $delegation->public_id,
                    assetPublicId: $resource->resource_public_reference,
                    resourcePublicReference: $resource->resource_public_reference,
                    correlationId: $correlationId,
                    idempotencyKey: $idempotencyKey,
                ));
            } elseif ($port instanceof DelegatedPrinterAssetPort) {
                $port->release(new DelegatedPrinterReleaseRequest(
                    organizerTenantId: (string) $delegation->organizer_tenant_id,
                    eventPublicId: (string) $delegation->event_id,
                    delegationPublicId: $delegation->public_id,
                    assetPublicId: $resource->resource_public_reference,
                    resourcePublicReference: $resource->resource_public_reference,
                    correlationId: $correlationId,
                    idempotencyKey: $idempotencyKey,
                ));
            } else {
                $port->release(new DelegatedScannerReleaseRequest(
                    organizerTenantId: (string) $delegation->organizer_tenant_id,
                    eventPublicId: (string) $delegation->event_id,
                    delegationPublicId: $delegation->public_id,
                    assetPublicId: $resource->resource_public_reference,
                    resourcePublicReference: $resource->resource_public_reference,
                    correlationId: $correlationId,
                    idempotencyKey: $idempotencyKey,
                ));
            }
        } catch (\Throwable) {
            // Release failures are logged but do not restore delegation status
        }
    }
}
