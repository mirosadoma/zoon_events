<?php

namespace App\Modules\Kiosk\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Modules\Kiosk\Domain\Context\KioskSessionContextStore;
use App\Modules\Kiosk\Http\Requests\KioskScanRequest;
use App\Modules\Scanning\Application\Actions\SubmitScanAction;
use App\Modules\Scanning\Domain\ValueObjects\ScanContext;
use App\Modules\Scanning\Http\Resources\ScanResultResource;
use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSetting;
use App\Modules\Shared\Http\Problems\Phase3Problem;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

final class KioskScanController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly KioskSessionContextStore $kioskContexts) {}

    public function store(KioskScanRequest $request, SubmitScanAction $action): JsonResponse
    {
        $context = $this->kioskContexts->current();
        $credentialId = $request->input('credential_id');

        $settings = EventCheckInSetting::query()
            ->where('tenant_id', $context->tenantId)
            ->where('event_id', $context->eventId)
            ->first();

        if ($settings?->lookup_confirmation_required && is_string($credentialId) && $credentialId !== '') {
            $confirmed = Cache::get(
                "kiosk-lookup-confirmed:{$context->tenantId}:{$context->eventId}:{$context->kioskId}:{$credentialId}"
            );

            if ($confirmed !== true) {
                throw Phase3Problem::make('lookup_confirmation_required');
            }
        }

        $submission = $action->execute(new ScanContext(
            tenantId: $context->tenantId,
            eventId: $context->eventId,
            scannerId: $context->kioskId,
            scannerType: 'kiosk',
            qrPayload: $request->string('qr_payload', '')->toString(),
            credentialId: $credentialId,
            override: false,
            overrideReason: null,
            actorCanOverride: false,
        ));

        return $this->success((new ScanResultResource($submission))->resolve());
    }
}
