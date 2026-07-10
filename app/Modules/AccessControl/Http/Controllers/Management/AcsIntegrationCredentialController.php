<?php

namespace App\Modules\AccessControl\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Modules\AccessControl\Application\Actions\RegisterAcsIntegrationCredentialAction;
use App\Modules\AccessControl\Http\Requests\AcsIntegrationCredentialRequest;
use App\Modules\Authorization\Policies\Phase4\Phase4Policy;
use App\Modules\Shared\Http\Problems\Phase4Problem;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;

final class AcsIntegrationCredentialController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly Phase4Policy $policy,
        private readonly AcsScopedEventGuard $scopedEvent,
    ) {}

    public function store(
        AcsIntegrationCredentialRequest $request,
        string $eventId,
        RegisterAcsIntegrationCredentialAction $action,
    ): JsonResponse {
        $user = $request->user();

        if ($user === null || ! $this->policy->allows($user, 'configureAcs')) {
            throw Phase4Problem::make('acs_config_not_permitted');
        }

        $this->scopedEvent->assertExists($eventId);

        $context = $this->contexts->current();
        $result = $action->execute(
            $context->tenant->id,
            $eventId,
            $request->string('name')->toString(),
            $request->input('capabilities', []),
        );

        return $this->success([
            'id' => $result['id'],
            'name' => $result['name'],
            'secret' => $result['secret'],
            'capabilities' => $result['capabilities'],
            'expires_at' => $result['expiresAt']->format(DATE_ATOM),
        ], 201);
    }
}
