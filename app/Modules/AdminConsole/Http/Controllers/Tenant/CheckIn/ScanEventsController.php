<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn\Concerns\AuthorizesTenantEventPage;
use App\Modules\AdminConsole\ViewModels\CheckIn\ScanEventsViewModel;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
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

        $query = ScanEvent::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->latest('scanned_at')
            ->limit(200);

        if ($request->filled('result')) {
            $query->where('result', $request->string('result')->toString());
        }

        if ($request->filled('scanner_type')) {
            $query->where('scanner_type', $request->string('scanner_type')->toString());
        }

        if ($request->boolean('offline')) {
            $query->where('offline_mode', true);
        }

        $rows = $query->get();
        $laneIds = $rows->pluck('gate_id')->filter()->unique()->values()->all();
        $zoneIds = $rows->pluck('zone_id')->filter()->unique()->values()->all();

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

        return Inertia::render('tenant/checkin/ScanEvents', $this->viewModel->index($event, $rows, $laneNames, $zoneNames));
    }
}
