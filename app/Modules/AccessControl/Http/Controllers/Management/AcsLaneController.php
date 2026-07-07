<?php

namespace App\Modules\AccessControl\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Modules\AccessControl\Application\Actions\CreateAcsLaneAction;
use App\Modules\AccessControl\Http\Requests\AcsLaneRequest;
use App\Modules\AccessControl\Http\Resources\AcsLaneResource;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\Authorization\Policies\Phase4\Phase4Policy;
use App\Modules\Shared\Http\Problems\Phase4Problem;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AcsLaneController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly Phase4Policy $policy,
        private readonly AcsScopedEventGuard $scopedEvent,
    ) {}

    public function store(AcsLaneRequest $request, string $eventId, CreateAcsLaneAction $action): JsonResponse
    {
        $this->authorizeConfigure($request);
        $this->scopedEvent->assertExists($eventId);

        $context = $this->contexts->current();
        $lane = $action->execute($context->tenant->id, $eventId, $request->validated());

        return $this->success((new AcsLaneResource($lane))->resolve(), 201);
    }

    public function index(Request $request, string $eventId): JsonResponse
    {
        $this->authorizeConfigure($request);

        $context = $this->contexts->current();
        $lanes = AcsLane::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $eventId)
            ->orderBy('name')
            ->get()
            ->map(fn (AcsLane $lane) => (new AcsLaneResource($lane))->resolve())
            ->values()
            ->all();

        return $this->success($lanes);
    }

    private function authorizeConfigure(Request $request): void
    {
        $user = $request->user();

        if ($user === null || ! $this->policy->allows($user, 'configureAcs')) {
            throw Phase4Problem::make('acs_config_not_permitted');
        }
    }
}
