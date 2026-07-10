<?php

namespace App\Modules\Kiosk\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Modules\BadgePrinting\Application\Actions\CreateBadgePrintJobAction;
use App\Modules\BadgePrinting\Http\Resources\BadgePrintJobResource;
use App\Modules\Kiosk\Domain\Context\KioskSessionContextStore;
use App\Modules\Kiosk\Http\Requests\KioskBadgePrintRequest;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\JsonResponse;

final class KioskBadgePrintController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly KioskSessionContextStore $kioskContexts) {}

    public function store(KioskBadgePrintRequest $request, CreateBadgePrintJobAction $action): JsonResponse
    {
        $context = $this->kioskContexts->current();

        $job = $action->execute(
            tenantId: $context->tenantId,
            eventId: $context->eventId,
            attendeeId: $request->string('attendee_id')->toString(),
            credentialId: $request->string('credential_id')->toString(),
            kioskId: $context->kioskId,
            printedByUserId: null,
        );

        return $this->success((new BadgePrintJobResource($job))->resolve(), 201);
    }
}
