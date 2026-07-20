<?php

namespace App\Modules\Kiosk\Application\Services;

use App\Modules\Kiosk\Application\Contracts\DelegatedKioskAssetPort;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskPortResult;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskProvisionRequest;
use App\Modules\Kiosk\Application\Contracts\DelegatedKioskReleaseRequest;
use Illuminate\Support\Facades\DB;

final class DatabaseDelegatedKioskAssetPort implements DelegatedKioskAssetPort
{
    public function provision(DelegatedKioskProvisionRequest $request): DelegatedKioskPortResult
    {
        $existing = DB::table('kiosks')
            ->where('tenant_id', $request->organizerTenantId)
            ->where('delegation_public_id', $request->delegationPublicId)
            ->where('venue_asset_public_id', $request->assetPublicId)
            ->first();

        if ($existing !== null) {
            return new DelegatedKioskPortResult(
                'provisioned',
                'kiosk',
                $request->assetPublicId,
                $request->capabilities,
            );
        }

        return DB::transaction(function () use ($request): DelegatedKioskPortResult {
            $candidate = DB::table('kiosks')
                ->where('tenant_id', $request->organizerTenantId)
                ->whereNull('delegation_public_id')
                ->lockForUpdate()
                ->limit(1)
                ->first();

            if ($candidate === null) {
                return new DelegatedKioskPortResult(
                    'degraded',
                    'kiosk',
                    null,
                    reason: 'no_available_kiosk',
                );
            }

            DB::table('kiosks')
                ->where('id', $candidate->id)
                ->update([
                    'delegation_public_id' => $request->delegationPublicId,
                    'venue_asset_public_id' => $request->assetPublicId,
                    'organizer_tenant_id' => $request->organizerTenantId,
                    'event_id' => $request->eventPublicId,
                ]);

            return new DelegatedKioskPortResult(
                'provisioned',
                'kiosk',
                $request->assetPublicId,
                $request->capabilities,
            );
        });
    }

    public function release(DelegatedKioskReleaseRequest $request): DelegatedKioskPortResult
    {
        DB::table('kiosks')
            ->where('tenant_id', $request->organizerTenantId)
            ->where('delegation_public_id', $request->delegationPublicId)
            ->where('venue_asset_public_id', $request->assetPublicId)
            ->update([
                'delegation_public_id' => null,
                'venue_asset_public_id' => null,
                'organizer_tenant_id' => null,
                'event_id' => null,
            ]);

        return new DelegatedKioskPortResult(
            'released',
            'kiosk',
            $request->resourcePublicReference,
        );
    }
}
