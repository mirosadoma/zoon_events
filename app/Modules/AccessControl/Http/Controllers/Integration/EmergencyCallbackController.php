<?php

namespace App\Modules\AccessControl\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Modules\AccessControl\Application\Actions\ClearEmergencyAction;
use App\Modules\AccessControl\Application\Actions\RaiseEmergencyAction;
use App\Modules\AccessControl\Domain\Context\AcsIntegrationContextStore;
use App\Modules\AccessControl\Http\Requests\EmergencySignalRequest;
use App\Modules\AccessControl\Http\Resources\EmergencyEventResource;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\Shared\Http\Problems\Phase4Problem;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\JsonResponse;

final class EmergencyCallbackController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly AcsIntegrationContextStore $contexts) {}

    public function store(
        EmergencySignalRequest $request,
        RaiseEmergencyAction $raise,
        ClearEmergencyAction $clear,
    ): JsonResponse {
        $context = $this->contexts->current();
        $action = $request->string('action')->toString();
        $occurredAt = $request->date('occurred_at');
        $zoneId = null;

        $externalZoneId = $request->input('external_acs_zone_id');
        if (is_string($externalZoneId) && $externalZoneId !== '') {
            $zone = AcsZone::query()
                ->where('tenant_id', $context->tenantId)
                ->where('event_id', $context->eventId)
                ->where('external_acs_zone_id', $externalZoneId)
                ->first();

            if ($zone === null) {
                throw Phase4Problem::make('acs_zone_unmapped');
            }

            $zoneId = $zone->id;
        }

        $signalSource = $request->input('signal_source', 'acs');

        if ($action === 'raise') {
            $emergency = $raise->execute(
                $context->tenantId,
                $context->eventId,
                $zoneId,
                is_string($signalSource) ? $signalSource : 'acs',
                $occurredAt,
            );

            return $this->success((new EmergencyEventResource($emergency))->resolve(), 202);
        }

        $cleared = $clear->execute(
            $context->tenantId,
            $context->eventId,
            $zoneId,
            $occurredAt,
        );

        return $this->success(
            $cleared->map(fn ($event) => (new EmergencyEventResource($event))->resolve())->values()->all(),
            202,
        );
    }
}
