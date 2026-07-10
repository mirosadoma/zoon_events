<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn\Concerns\AuthorizesTenantEventPage;
use App\Modules\AdminConsole\ViewModels\CheckIn\CheckInDashboardViewModel;
use App\Modules\Authorization\Application\PermissionEvaluator;
use Inertia\Inertia;
use Inertia\Response;

final class ScannerController extends Controller
{
    use AuthorizesTenantEventPage;

    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly CheckInDashboardViewModel $viewModel,
    ) {}

    public function show(string $eventId): Response
    {
        [$context, $event] = $this->authorizeTenantEvent(
            $this->sessions,
            $this->permissions,
            $eventId,
            'checkin.scan.submit',
        );

        return Inertia::render(
            'tenant/checkin/Scanner',
            $this->viewModel->make($event, $context->tenant->id),
        );
    }
}
