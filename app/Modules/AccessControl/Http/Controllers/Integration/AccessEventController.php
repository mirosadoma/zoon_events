<?php

namespace App\Modules\AccessControl\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Modules\AccessControl\Application\Actions\IngestAccessEventAction;
use App\Modules\AccessControl\Domain\Context\AcsIntegrationContextStore;
use App\Modules\AccessControl\Http\Requests\AccessEventCallbackRequest;
use App\Modules\AccessControl\Http\Resources\AccessEventResource;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\JsonResponse;

final class AccessEventController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly AcsIntegrationContextStore $contexts) {}

    public function store(AccessEventCallbackRequest $request, IngestAccessEventAction $action): JsonResponse
    {
        $context = $this->contexts->current();
        $credentialReference = $request->input('credential_reference');

        $event = $action->execute(
            $context,
            $request->string('external_event_id')->toString(),
            $request->string('external_acs_lane_id')->toString(),
            $request->string('event_type')->toString(),
            $request->date('occurred_at'),
            is_string($credentialReference) ? $credentialReference : null,
        );

        return $this->success((new AccessEventResource($event))->resolve(), 202);
    }
}
