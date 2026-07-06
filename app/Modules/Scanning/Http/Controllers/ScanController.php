<?php

namespace App\Modules\Scanning\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authorization\Policies\Phase2\Phase2Policy;
use App\Modules\Scanning\Application\Actions\SubmitScanAction;
use App\Modules\Scanning\Domain\ValueObjects\ScanContext;
use App\Modules\Scanning\Http\Requests\SubmitScanRequest;
use App\Modules\Scanning\Http\Resources\ScanResultResource;
use App\Modules\Shared\Http\Problems\Phase2Problem;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;

final class ScanController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly Phase2Policy $policy,
    ) {}

    public function store(SubmitScanRequest $request, string $eventId, SubmitScanAction $action): JsonResponse
    {
        $user = $request->user();
        if ($user === null || ! $this->policy->allows($user, 'submitScan')) {
            abort(403);
        }

        if ($request->boolean('offline_mode')) {
            throw Phase2Problem::make('online_endpoint_does_not_accept_offline_mode');
        }

        $override = $request->boolean('override');
        if ($override) {
            if (! $this->policy->allows($user, 'overrideScan')) {
                throw Phase2Problem::make('override_not_permitted');
            }
            if ($request->string('override_reason')->trim()->isEmpty()) {
                throw Phase2Problem::make('override_reason_required');
            }
        }

        $context = $this->contexts->current();

        $submission = $action->execute(new ScanContext(
            tenantId: $context->tenant->id,
            eventId: $eventId,
            scannerId: (string) $user->id,
            scannerType: $request->string('scanner_type')->toString(),
            qrPayload: $request->string('qr_payload')->toString(),
            override: $override,
            overrideReason: $request->input('override_reason'),
            actorCanOverride: $this->policy->allows($user, 'overrideScan'),
            offlineMode: false,
            scannedAt: null,
        ));

        return $this->success((new ScanResultResource($submission))->resolve());
    }
}
