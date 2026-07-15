<?php

namespace App\Modules\Scanning\Application\Services;

use App\Modules\Scanning\Application\Contracts\DelegatedScannerAssetPort;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerPortResult;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerProvisionRequest;
use App\Modules\Scanning\Application\Contracts\DelegatedScannerReleaseRequest;
use Illuminate\Support\Facades\DB;

final class DatabaseDelegatedScannerAssetPort implements DelegatedScannerAssetPort
{
    public function provision(DelegatedScannerProvisionRequest $request): DelegatedScannerPortResult
    {
        $existing = DB::table('delegated_scanner_allocations')
            ->where('tenant_id', $request->organizerTenantId)
            ->where('delegation_public_id', $request->delegationPublicId)
            ->where('idempotency_key_hash', hash('sha256', $request->idempotencyKey))
            ->whereNull('released_at')
            ->first();

        if ($existing !== null) {
            return new DelegatedScannerPortResult(
                'provisioned',
                'scanner',
                $request->assetPublicId,
                $request->capabilities,
            );
        }

        DB::table('delegated_scanner_allocations')->insert([
            'tenant_id' => $request->organizerTenantId,
            'organizer_tenant_id' => $request->organizerTenantId,
            'event_id' => $request->eventPublicId,
            'delegation_public_id' => $request->delegationPublicId,
            'venue_asset_public_id' => $request->assetPublicId,
            'scanner_public_id' => $request->assetPublicId,
            'granted_capabilities' => json_encode($request->capabilities),
            'starts_at' => $request->startsAt->format('Y-m-d H:i:s.u'),
            'ends_at' => $request->endsAt->format('Y-m-d H:i:s.u'),
            'idempotency_key_hash' => hash('sha256', $request->idempotencyKey),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new DelegatedScannerPortResult(
            'provisioned',
            'scanner',
            $request->assetPublicId,
            $request->capabilities,
        );
    }

    public function release(DelegatedScannerReleaseRequest $request): DelegatedScannerPortResult
    {
        DB::table('delegated_scanner_allocations')
            ->where('tenant_id', $request->organizerTenantId)
            ->where('delegation_public_id', $request->delegationPublicId)
            ->where('venue_asset_public_id', $request->assetPublicId)
            ->whereNull('released_at')
            ->update(['released_at' => now()]);

        return new DelegatedScannerPortResult(
            'released',
            'scanner',
            $request->resourcePublicReference,
        );
    }
}
