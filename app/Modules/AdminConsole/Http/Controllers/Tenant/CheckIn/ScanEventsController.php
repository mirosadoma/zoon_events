<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn;

use App\Http\Controllers\Controller;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\Application\Support\InertiaListPaginator;
use App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn\Concerns\AuthorizesTenantEventPage;
use App\Modules\AdminConsole\ViewModels\CheckIn\ScanEventsViewModel;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ScanEventsController extends Controller
{
    use AuthorizesTenantEventPage;

    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly ScanEventsViewModel $viewModel,
    ) {}

    public function index(Request $request, string $eventId): Response
    {
        [$context, $event] = $this->authorizeTenantEvent(
            $this->sessions,
            $this->permissions,
            $eventId,
            'checkin.dashboard.view',
        );

        $filters = $this->filters($request);

        $query = ScanEvent::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->when($filters['result'] !== '', fn ($builder) => $builder->where('result', $filters['result']))
            ->when($filters['scanner_type'] !== '', fn ($builder) => $builder->where('scanner_type', $filters['scanner_type']))
            ->when($filters['offline'], fn ($builder) => $builder->where('offline_mode', true))
            ->latest('scanned_at')
            ->orderByDesc('id');

        $result = InertiaListPaginator::paginate($query, $request);
        $laneIds = $result['items']->pluck('gate_id')->filter()->unique()->values()->all();
        $zoneIds = $result['items']->pluck('zone_id')->filter()->unique()->values()->all();

        $laneNames = $laneIds === [] ? [] : AcsLane::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->whereIn('id', $laneIds)
            ->pluck('name', 'id')
            ->all();

        $zoneNames = $zoneIds === [] ? [] : AcsZone::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->whereIn('id', $zoneIds)
            ->pluck('name', 'id')
            ->all();

        return Inertia::render(
            'tenant/checkin/ScanEvents',
            $this->viewModel->index($event, $result['items'], $laneNames, $zoneNames, $filters, $result['pagination']),
        );
    }

    /**
     * @return array{result: string, scanner_type: string, offline: bool}
     */
    private function filters(Request $request): array
    {
        $result = trim((string) $request->query('result', ''));
        $allowedResults = [
            'accepted', 'manual_override', 'duplicate', 'revoked', 'expired',
            'rejected', 'unauthorized_zone', 'anti_passback_rejected',
        ];
        if (! in_array($result, $allowedResults, true)) {
            $result = '';
        }

        $scannerType = trim((string) $request->query('scanner_type', ''));
        $allowedScanners = [
            'staff_phone', 'handheld_scanner', 'kiosk', 'gate', 'acs_lane', 'acs_gate', 'manual_desk',
        ];
        if (! in_array($scannerType, $allowedScanners, true)) {
            $scannerType = '';
        }

        return [
            'result' => $result,
            'scanner_type' => $scannerType,
            'offline' => $request->boolean('offline'),
        ];
    }
}
