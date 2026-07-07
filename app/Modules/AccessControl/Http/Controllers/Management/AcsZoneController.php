<?php

namespace App\Modules\AccessControl\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Modules\AccessControl\Application\Actions\CreateAcsZoneAction;
use App\Modules\AccessControl\Application\Actions\UpdateAcsZoneAction;
use App\Modules\AccessControl\Http\Requests\AcsZoneRequest;
use App\Modules\AccessControl\Http\Requests\UpdateAcsZoneRequest;
use App\Modules\AccessControl\Http\Resources\AcsZoneResource;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\Authorization\Policies\Phase4\Phase4Policy;
use App\Modules\Shared\Http\Problems\Phase4Problem;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AcsZoneController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly Phase4Policy $policy,
        private readonly AcsScopedEventGuard $scopedEvent,
    ) {}

    public function store(AcsZoneRequest $request, string $eventId, CreateAcsZoneAction $action): JsonResponse
    {
        $this->authorizeConfigure($request);
        $this->scopedEvent->assertExists($eventId);

        $context = $this->contexts->current();
        $zone = $action->execute($context->tenant->id, $eventId, $request->validated());

        return $this->success((new AcsZoneResource($zone))->resolve(), 201);
    }

    public function index(Request $request, string $eventId): JsonResponse
    {
        $this->authorizeConfigure($request);

        $context = $this->contexts->current();
        $zones = AcsZone::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $eventId)
            ->orderBy('name')
            ->get()
            ->map(fn (AcsZone $zone) => (new AcsZoneResource($zone))->resolve())
            ->values()
            ->all();

        return $this->success($zones);
    }

    public function update(
        UpdateAcsZoneRequest $request,
        string $eventId,
        string $zoneId,
        UpdateAcsZoneAction $action,
    ): JsonResponse {
        $this->authorizeConfigure($request);
        $this->scopedEvent->assertExists($eventId);

        $context = $this->contexts->current();
        $zone = AcsZone::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $eventId)
            ->findOrFail($zoneId);

        $updated = $action->execute($zone, $request->validated());

        return $this->success((new AcsZoneResource($updated))->resolve());
    }

    private function authorizeConfigure(Request $request): void
    {
        $user = $request->user();

        if ($user === null || ! $this->policy->allows($user, 'configureAcs')) {
            throw Phase4Problem::make('acs_config_not_permitted');
        }
    }
}
