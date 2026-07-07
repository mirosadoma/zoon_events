<?php

namespace App\Modules\Kiosk\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Modules\Authorization\Policies\Phase3\Phase3Policy;
use App\Modules\Kiosk\Application\Actions\RegisterKioskAction;
use App\Modules\Kiosk\Application\Actions\RetireKioskAction;
use App\Modules\Kiosk\Domain\KioskStatusDeriver;
use App\Modules\Kiosk\Http\Requests\RegisterKioskRequest;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSetting;
use App\Modules\Shared\Contracts\Clock;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class KioskController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly Phase3Policy $policy,
        private readonly KioskStatusDeriver $deriver,
        private readonly Clock $clock,
    ) {}

    public function store(
        RegisterKioskRequest $request,
        string $eventId,
        RegisterKioskAction $action,
    ): JsonResponse {
        $user = $request->user();

        if ($user === null || ! $this->policy->allows($user, 'manageKiosk')) {
            abort(403);
        }

        $context = $this->contexts->current();

        $kiosk = $action->execute(
            tenantId: $context->tenant->id,
            eventId: $eventId,
            deviceName: $request->string('device_name')->toString(),
            locationLabel: $request->input('location_label'),
            confirmationRequired: $request->boolean('confirmation_required'),
            plainConfirmationCode: $request->input('confirmation_code'),
        );

        return $this->success($this->kioskToArray($kiosk, $eventId, $context->tenant->id), 201);
    }

    public function index(Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();

        if ($user === null || (! $this->policy->allows($user, 'manageKiosk') && ! $this->policy->allows($user, 'viewKioskHealth'))) {
            abort(403);
        }

        $context = $this->contexts->current();
        $tenantId = $context->tenant->id;

        $settings = EventCheckInSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        $threshold = $settings?->kiosk_offline_threshold_seconds
            ?? (int) config('printing.kiosk.default_offline_threshold_seconds', 120);

        $kiosks = Kiosk::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->get()
            ->map(fn (Kiosk $k) => $this->kioskToArray($k, $eventId, $tenantId, $threshold))
            ->values()
            ->all();

        return $this->success($kiosks);
    }

    public function retire(Request $request, string $eventId, string $kioskId, RetireKioskAction $action): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $this->policy->allows($user, 'manageKiosk')) {
            abort(403);
        }

        $context = $this->contexts->current();

        $kiosk = Kiosk::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $eventId)
            ->findOrFail($kioskId);

        $action->execute($kiosk);

        return $this->success(['status' => 'retired']);
    }

    private function kioskToArray(Kiosk $kiosk, string $eventId, string $tenantId, int $threshold = 120): array
    {
        $derivedStatus = $this->deriver->derive($kiosk, $threshold, $this->clock->now());

        return [
            'id' => $kiosk->id,
            'device_name' => $kiosk->device_name,
            'device_code' => $kiosk->device_code,
            'status' => $derivedStatus,
            'printer_status' => $kiosk->printer_status,
            'last_heartbeat_at' => $kiosk->last_heartbeat_at?->toIso8601String(),
            'confirmation_required' => $kiosk->confirmation_required,
        ];
    }
}
