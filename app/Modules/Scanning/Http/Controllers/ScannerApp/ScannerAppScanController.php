<?php

namespace App\Modules\Scanning\Http\Controllers\ScannerApp;

use App\Http\Controllers\Controller;
use App\Modules\Scanning\Application\Actions\SubmitScanAction;
use App\Modules\Scanning\Application\Support\ScanPayloadResolver;
use App\Modules\Scanning\Domain\Context\ScannerAppSessionContextStore;
use App\Modules\Scanning\Domain\ValueObjects\ScanContext;
use App\Modules\Scanning\Http\Resources\ScanResultResource;
use App\Modules\Shared\Http\Problems\Phase2Problem;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ScannerAppScanController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly ScannerAppSessionContextStore $sessions,
        private readonly ScanPayloadResolver $payloads,
    ) {}

    public function store(Request $request, SubmitScanAction $action): JsonResponse
    {
        $validated = $request->validate([
            'qr_payload' => ['sometimes', 'string', 'max:512'],
            'credential_id' => ['sometimes', 'string'],
        ]);

        $hasQr = $request->filled('qr_payload');
        $hasCredential = $request->filled('credential_id');
        if ((! $hasQr && ! $hasCredential) || ($hasQr && $hasCredential)) {
            throw Phase2Problem::make('scan_context_invalid');
        }

        $context = $this->sessions->current();

        $resolvedPayload = $hasCredential
            ? [
                'qr_payload' => '',
                'credential_id' => (string) $validated['credential_id'],
            ]
            : $this->payloads->resolveQrPayload(
                (string) $validated['qr_payload'],
                $context->tenantId,
                $context->eventId,
            );

        $submission = $action->execute(new ScanContext(
            tenantId: $context->tenantId,
            eventId: $context->eventId,
            scannerId: 'scanner-app:'.$context->sessionId,
            scannerType: 'staff_phone',
            qrPayload: $resolvedPayload['qr_payload'],
            credentialId: $resolvedPayload['credential_id'],
            override: false,
            overrideReason: null,
            actorCanOverride: false,
            offlineMode: false,
            scannedAt: null,
            zoneId: $context->eventZoneId,
        ));

        return $this->success((new ScanResultResource($submission))->resolve());
    }
}
