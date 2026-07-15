<?php

namespace App\Modules\BadgePrinting\Application\Services;

use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterAssetPort;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterPortResult;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterProvisionRequest;
use App\Modules\BadgePrinting\Application\Contracts\DelegatedPrinterReleaseRequest;
use Illuminate\Support\Facades\DB;

final class DatabaseDelegatedPrinterAssetPort implements DelegatedPrinterAssetPort
{
    public function provision(DelegatedPrinterProvisionRequest $request): DelegatedPrinterPortResult
    {
        $existing = DB::table('delegated_printer_allocations')
            ->where('tenant_id', $request->organizerTenantId)
            ->where('delegation_public_id', $request->delegationPublicId)
            ->where('idempotency_key_hash', hash('sha256', $request->idempotencyKey))
            ->whereNull('released_at')
            ->first();

        if ($existing !== null) {
            return new DelegatedPrinterPortResult(
                'provisioned',
                'printer',
                $request->assetPublicId,
                $request->capabilities,
            );
        }

        DB::table('delegated_printer_allocations')->insert([
            'tenant_id' => $request->organizerTenantId,
            'organizer_tenant_id' => $request->organizerTenantId,
            'event_id' => $request->eventPublicId,
            'delegation_public_id' => $request->delegationPublicId,
            'venue_asset_public_id' => $request->assetPublicId,
            'printer_public_id' => $request->assetPublicId,
            'granted_capabilities' => json_encode($request->capabilities),
            'starts_at' => $request->startsAt->format('Y-m-d H:i:s.u'),
            'ends_at' => $request->endsAt->format('Y-m-d H:i:s.u'),
            'idempotency_key_hash' => hash('sha256', $request->idempotencyKey),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new DelegatedPrinterPortResult(
            'provisioned',
            'printer',
            $request->assetPublicId,
            $request->capabilities,
        );
    }

    public function release(DelegatedPrinterReleaseRequest $request): DelegatedPrinterPortResult
    {
        DB::table('delegated_printer_allocations')
            ->where('tenant_id', $request->organizerTenantId)
            ->where('delegation_public_id', $request->delegationPublicId)
            ->where('venue_asset_public_id', $request->assetPublicId)
            ->whereNull('released_at')
            ->update(['released_at' => now()]);

        return new DelegatedPrinterPortResult(
            'released',
            'printer',
            $request->resourcePublicReference,
        );
    }
}
