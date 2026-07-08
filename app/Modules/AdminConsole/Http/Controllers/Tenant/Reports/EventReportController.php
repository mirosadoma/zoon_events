<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\Reports;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\Http\Controllers\Tenant\Admin\Concerns\AuthorizesTenantAdminPage;
use App\Modules\AdminConsole\ViewModels\Reports\EventReportViewModel;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Inertia\Inertia;
use Inertia\Response;

final class EventReportController extends Controller
{
    use AuthorizesTenantAdminPage;

    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly EventReportViewModel $viewModel,
    ) {}

    public function show(string $eventId): Response
    {
        $context = $this->authorizeTenantAdmin($this->sessions, $this->permissions, 'event.view');

        $event = Event::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($eventId);

        return Inertia::render('tenant/reports/EventReport', $this->viewModel->make($event, $context->tenant->id));
    }
}
