<?php

namespace App\Modules\Kiosk\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Modules\Kiosk\Application\Actions\RecordKioskHeartbeatAction;
use App\Modules\Kiosk\Domain\Context\KioskSessionContextStore;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class KioskHeartbeatController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly KioskSessionContextStore $kioskContexts) {}

    public function store(Request $request, RecordKioskHeartbeatAction $action): JsonResponse
    {
        $context = $this->kioskContexts->current();

        $kiosk = Kiosk::query()
            ->where('tenant_id', $context->tenantId)
            ->findOrFail($context->kioskId);

        $action->execute(
            $kiosk,
            $request->string('printer_status', 'unknown')->toString(),
            $request->input('printer_reason_code'),
            $request->input('app_version'),
        );

        return $this->success(['status' => 'ok']);
    }
}
