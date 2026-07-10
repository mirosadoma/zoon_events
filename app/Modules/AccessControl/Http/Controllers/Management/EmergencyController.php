<?php

namespace App\Modules\AccessControl\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Modules\AccessControl\Application\Actions\ClearEmergencyAction;
use App\Modules\AccessControl\Application\Actions\RaiseEmergencyAction;
use App\Modules\AccessControl\Http\Requests\OperatorEmergencyRequest;
use App\Modules\AccessControl\Http\Resources\EmergencyEventResource;
use App\Modules\Authorization\Policies\Phase4\Phase4Policy;
use App\Modules\Shared\Http\Problems\Phase4Problem;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;

final class EmergencyController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly Phase4Policy $policy,
        private readonly AcsScopedEventGuard $scopedEvent,
    ) {}

    public function store(
        OperatorEmergencyRequest $request,
        string $eventId,
        RaiseEmergencyAction $raise,
        ClearEmergencyAction $clear,
    ): JsonResponse {
        $user = $request->user();

        if ($user === null || ! $this->policy->allows($user, 'manageEmergency')) {
            throw Phase4Problem::make('acs_emergency_not_permitted');
        }

        $this->scopedEvent->assertExists($eventId);

        $context = $this->contexts->current();
        $zoneId = $request->input('zone_id');
        $action = $request->string('action')->toString();
        $now = now();

        if ($action === 'raise') {
            $emergency = $raise->execute(
                $context->tenant->id,
                $eventId,
                is_string($zoneId) ? $zoneId : null,
                'operator',
                $now,
            );

            return $this->success((new EmergencyEventResource($emergency))->resolve());
        }

        $cleared = $clear->execute(
            $context->tenant->id,
            $eventId,
            is_string($zoneId) ? $zoneId : null,
            $now,
        );

        return $this->success(
            $cleared->map(fn ($event) => (new EmergencyEventResource($event))->resolve())->values()->all(),
        );
    }
}
