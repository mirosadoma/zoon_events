<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn\Concerns\AuthorizesTenantEventPage;
use App\Modules\AdminConsole\ViewModels\CheckIn\CheckInDashboardViewModel;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Scanning\Application\Queries\GetCheckInSummaryQuery;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    use AuthorizesTenantEventPage;

    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly CheckInDashboardViewModel $viewModel,
        private readonly GetCheckInSummaryQuery $summaryQuery,
    ) {}

    public function show(string $eventId): Response
    {
        [$context, $event] = $this->authorizeTenantEvent(
            $this->sessions,
            $this->permissions,
            $eventId,
            'checkin.dashboard.view',
        );

        $summary = $this->summaryQuery->handle($context->tenant->id, $event->id);

        return Inertia::render(
            'tenant/checkin/Dashboard',
            $this->viewModel->make($event, $context->tenant->id, $summary),
        );
    }
}
