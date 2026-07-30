<?php

namespace App\Modules\Kiosk\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Modules\Kiosk\Application\Actions\ListKioskAttendeesAction;
use App\Modules\Kiosk\Domain\Context\KioskSessionContextStore;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\JsonResponse;

final class KioskAttendeesController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly KioskSessionContextStore $kioskContexts,
        private readonly ListKioskAttendeesAction $listAttendees,
    ) {}

    public function index(): JsonResponse
    {
        $context = $this->kioskContexts->current();

        return $this->success(
            $this->listAttendees->execute($context->tenantId, $context->eventId),
        );
    }
}
