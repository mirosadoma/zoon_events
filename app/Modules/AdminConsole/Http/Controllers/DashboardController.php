<?php

namespace App\Modules\AdminConsole\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\ViewModels\FoundationDashboardViewModel;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('FoundationDashboard', (new FoundationDashboardViewModel(
            'platform',
            'Foundation administration',
            ['tenants', 'users', 'roles', 'audit', 'health', 'feature_flags', 'configuration'],
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
