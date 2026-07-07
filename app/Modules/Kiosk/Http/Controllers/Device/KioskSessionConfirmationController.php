<?php

namespace App\Modules\Kiosk\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Modules\Kiosk\Application\Actions\ConfirmKioskSessionAction;
use App\Modules\Kiosk\Domain\Context\KioskSessionContextStore;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\KioskSession;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class KioskSessionConfirmationController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly KioskSessionContextStore $kioskContexts) {}

    public function store(Request $request, ConfirmKioskSessionAction $action): JsonResponse
    {
        $context = $this->kioskContexts->current();

        $kiosk = Kiosk::query()
            ->where('tenant_id', $context->tenantId)
            ->findOrFail($context->kioskId);

        $session = KioskSession::query()
            ->where('tenant_id', $context->tenantId)
            ->where('kiosk_id', $context->kioskId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->firstOrFail();

        $action->execute($session, $kiosk, $request->string('confirmation_code', '')->toString());

        return $this->success(['confirmed' => true]);
    }
}
