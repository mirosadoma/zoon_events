<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\Kiosk;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\Application\Support\InertiaListPaginator;
use App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn\Concerns\AuthorizesTenantEventPage;
use App\Modules\AdminConsole\ViewModels\Kiosk\KioskViewModel;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgePrintJob;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Scanning\Infrastructure\Persistence\Models\ScanEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EventKioskController extends Controller
{
    use AuthorizesTenantEventPage;

    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly KioskViewModel $viewModel,
    ) {}

    public function index(Request $request, string $eventId): Response
    {
        [$context, $event] = $this->authorizeTenantEvent(
            $this->sessions,
            $this->permissions,
            $eventId,
            'kiosk.health.view',
        );

        $threshold = $this->viewModel->offlineThreshold($context->tenant->id, $event->id);
        $query = Kiosk::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->orderBy('device_name')
            ->orderBy('id');

        $result = InertiaListPaginator::paginate($query, $request);

        return Inertia::render(
            'tenant/kiosk/Index',
            $this->viewModel->index($event, $context->tenant->id, $result['items'], $threshold, $result['pagination']),
        );
    }

    public function show(string $eventId, string $kioskId): Response
    {
        [$context, $event] = $this->authorizeTenantEvent(
            $this->sessions,
            $this->permissions,
            $eventId,
            'kiosk.health.view',
        );

        $threshold = $this->viewModel->offlineThreshold($context->tenant->id, $event->id);

        $kiosk = Kiosk::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->findOrFail($this->routeParamOrNull('kiosk_id') ?? $kioskId);

        $recentCheckins = ScanEvent::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->where('scanner_type', 'kiosk')
            ->where('scanner_id', $kiosk->id)
            ->latest('scanned_at')
            ->limit(10)
            ->get();

        $recentPrintJobs = BadgePrintJob::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->where('kiosk_id', $kiosk->id)
            ->latest('created_at')
            ->limit(10)
            ->get();

        return Inertia::render(
            'tenant/kiosk/Detail',
            $this->viewModel->detail(
                $event,
                $context->tenant->id,
                $kiosk,
                $threshold,
                $recentCheckins,
                $recentPrintJobs,
            ),
        );
    }
}
