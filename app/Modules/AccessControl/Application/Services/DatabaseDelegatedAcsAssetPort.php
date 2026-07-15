<?php

namespace App\Modules\AccessControl\Application\Services;

use App\Modules\AccessControl\Application\Contracts\DelegatedAcsAssetPort;
use App\Modules\AccessControl\Application\Contracts\DelegatedAcsPortResult;
use App\Modules\AccessControl\Application\Contracts\DelegatedAcsProvisionRequest;
use App\Modules\AccessControl\Application\Contracts\DelegatedAcsReleaseRequest;
use Illuminate\Support\Facades\DB;

final class DatabaseDelegatedAcsAssetPort implements DelegatedAcsAssetPort
{
    public function provision(DelegatedAcsProvisionRequest $request): DelegatedAcsPortResult
    {
        $table = match ($request->assetType) {
            'access_zone' => 'acs_zones',
            'access_lane', 'turnstile', 'security_gate' => 'acs_lanes',
            default => null,
        };

        if ($table === null) {
            return new DelegatedAcsPortResult(
                'not_applicable',
                $request->assetType,
                null,
                reason: 'unsupported_asset_type',
            );
        }

        $existing = DB::table($table)
            ->where('tenant_id', $request->organizerTenantId)
            ->where('delegation_public_id', $request->delegationPublicId)
            ->where('venue_asset_public_id', $request->assetPublicId)
            ->first();

        if ($existing !== null) {
            return new DelegatedAcsPortResult(
                'provisioned',
                $request->assetType,
                $request->assetPublicId,
                $request->capabilities,
            );
        }

        return DB::transaction(function () use ($table, $request): DelegatedAcsPortResult {
            $candidate = DB::table($table)
                ->where('tenant_id', $request->organizerTenantId)
                ->whereNull('delegation_public_id')
                ->lockForUpdate()
                ->limit(1)
                ->first();

            if ($candidate === null) {
                return new DelegatedAcsPortResult(
                    'degraded',
                    $request->assetType,
                    null,
                    reason: 'no_available_resource',
                );
            }

            DB::table($table)
                ->where('id', $candidate->id)
                ->update([
                    'delegation_public_id' => $request->delegationPublicId,
                    'venue_asset_public_id' => $request->assetPublicId,
                    'organizer_tenant_id' => $request->organizerTenantId,
                    'event_id' => $request->eventPublicId,
                ]);

            return new DelegatedAcsPortResult(
                'provisioned',
                $request->assetType,
                $request->assetPublicId,
                $request->capabilities,
            );
        });
    }

    public function release(DelegatedAcsReleaseRequest $request): DelegatedAcsPortResult
    {
        foreach (['acs_zones', 'acs_lanes'] as $table) {
            DB::table($table)
                ->where('tenant_id', $request->organizerTenantId)
                ->where('delegation_public_id', $request->delegationPublicId)
                ->where('venue_asset_public_id', $request->assetPublicId)
                ->update([
                    'delegation_public_id' => null,
                    'venue_asset_public_id' => null,
                    'organizer_tenant_id' => null,
                    'event_id' => null,
                ]);
        }

        return new DelegatedAcsPortResult(
            'released',
            'access_resource',
            $request->resourcePublicReference,
        );
    }
}
