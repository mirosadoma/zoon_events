<?php

namespace App\Modules\Kiosk\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Modules\Kiosk\Domain\Context\KioskSessionContextStore;
use App\Modules\Scanning\Application\Actions\SubmitScanAction;
use App\Modules\Scanning\Domain\ValueObjects\ScanContext;
use App\Modules\Scanning\Http\Resources\ScanResultResource;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class KioskScanController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly KioskSessionContextStore $kioskContexts) {}

    public function store(Request $request, SubmitScanAction $action): JsonResponse
    {
        $context = $this->kioskContexts->current();

        $submission = $action->execute(new ScanContext(
            tenantId: $context->tenantId,
            eventId: $context->eventId,
            scannerId: $context->kioskId,
            scannerType: 'kiosk',
            qrPayload: $request->string('qr_payload', '')->toString(),
            credentialId: $request->input('credential_id'),
            override: false,
            overrideReason: null,
            actorCanOverride: false,
        ));

        return $this->success((new ScanResultResource($submission))->resolve());
    }
}
