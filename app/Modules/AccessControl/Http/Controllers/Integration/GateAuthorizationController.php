<?php

namespace App\Modules\AccessControl\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Modules\AccessControl\Application\Actions\AuthorizeGateAction;
use App\Modules\AccessControl\Domain\Context\AcsIntegrationContextStore;
use App\Modules\AccessControl\Http\Requests\AuthorizeGateRequest;
use App\Modules\AccessControl\Http\Resources\AuthorizationDecisionResource;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\JsonResponse;

final class GateAuthorizationController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly AcsIntegrationContextStore $contexts) {}

    public function store(AuthorizeGateRequest $request, AuthorizeGateAction $action): JsonResponse
    {
        $context = $this->contexts->current();
        $credentialReference = $request->input('credential_reference');

        $result = $action->execute(
            $context,
            $request->string('external_acs_lane_id')->toString(),
            is_string($credentialReference) ? $credentialReference : null,
            $request->string('direction')->toString(),
        );

        return $this->success((new AuthorizationDecisionResource($result))->resolve());
    }
}
