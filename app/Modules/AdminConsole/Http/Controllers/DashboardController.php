<?php

namespace App\Modules\AdminConsole\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\DashboardOverviewBuilder;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\ViewModels\Admin\ProfileViewModel;
use App\Modules\AdminConsole\ViewModels\FoundationDashboardViewModel;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardOverviewBuilder $overview,
        private readonly SessionContextBuilder $sessionContext,
        private readonly TenantContextStore $contexts,
    ) {}

    public function __invoke(): Response
    {
        $user = request()->user();
        $context = $this->contexts->currentOrNull()
            ?? ($user !== null ? $this->sessionContext->tenantContextFor($user) : null);

        return Inertia::render('FoundationDashboard', (new FoundationDashboardViewModel(
            $context !== null ? 'tenant' : 'platform',
            'Dashboard overview',
            ['events', 'orders', 'attendees', 'credentials', 'checkin', 'audit'],
            $this->overview->build($context),
        ))->toArray());
    }

    public function profile(): Response
    {
        $user = request()->user();
        abort_if($user === null, 403);
        $built = $this->sessionContext->build(request());
        $session = $built['session'];

        return Inertia::render('Profile', (new ProfileViewModel(
            $user,
            is_array($session) ? (string) ($session['role_label'] ?? 'Operator') : 'Operator',
            is_array($session) ? ($session['tenant'] ?? null) : null,
        ))->toArray());
    }

    public function section(string $section): Response
    {
        $permission = [
            'tenants' => 'platform.tenant.view',
            'users' => 'platform.user.view',
            'roles' => 'platform.role.view',
            'audit' => 'platform.audit.view',
            'health' => 'operations.health.view',
            'feature-flags' => 'platform.feature_flag.view',
            'configuration' => 'platform.configuration.view',
        ][$section] ?? null;

        abort_if($permission === null, 404);
        Gate::authorize($permission);

        return Inertia::render('DashboardSection', [
            'section' => $section,
            'scope' => 'platform',
            'items' => [],
        ]);
    }
}
