<?php

namespace App\Modules\Scanning\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authorization\Policies\Phase2\Phase2Policy;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Scanning\Application\Actions\ReconcileOfflineScanBatchAction;
use App\Modules\Scanning\Http\Requests\OfflineScanBatchRequest;
use App\Modules\Scanning\Http\Resources\OfflineScanBatchResource;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;

final class OfflineScanBatchController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly Phase2Policy $policy,
    ) {}

    public function store(OfflineScanBatchRequest $request, string $eventId, ReconcileOfflineScanBatchAction $action): JsonResponse
    {
        $user = $request->user();
        if ($user === null || ! $this->policy->allows($user, 'submitScan')) {
            abort(403);
        }

        $tenantId = $this->contexts->current()->tenant->id;
        Event::query()->where('tenant_id', $tenantId)->findOrFail($eventId);

        $batch = $action->execute(
            $tenantId,
            $eventId,
            $request->string('device_reference')->toString(),
            $request->input('scans'),
            (string) $user->id,
            'staff_phone',
            $this->policy->allows($user, 'overrideScan'),
        );

        return $this->success((new OfflineScanBatchResource($batch))->resolve(), 202);
    }
}
