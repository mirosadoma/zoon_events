<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\ViewModels\Marketplace\TenantStatementViewModel;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TenantStatementPageController extends Controller
{
    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly TenantStatementViewModel $viewModel,
    ) {}

    public function index(Request $request): Response
    {
        $context = $this->authorizeStatements();

        return Inertia::render(
            'tenant/marketplace/statements/Index',
            $this->viewModel->index(
                $context->tenant->id,
                $request->only(['status', 'dispute_status', 'from', 'to', 'cursor']),
                $this->statementPermissions($context),
            ),
        );
    }

    public function show(string $statementPublicId): Response
    {
        $context = $this->authorizeStatements();

        return Inertia::render(
            'tenant/marketplace/statements/Show',
            $this->viewModel->show(
                $context->tenant->id,
                $statementPublicId,
                $this->statementPermissions($context),
            ),
        );
    }

    private function authorizeStatements(): TenantContext
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $context = $this->sessions->tenantContextFor($user);
        abort_unless($context instanceof TenantContext, 403);
        abort_unless($this->permissions->hasTenantPermission($context, 'reports.view'), 403);

        return $context;
    }

    /**
     * @return array<string, bool>
     */
    private function statementPermissions(TenantContext $context): array
    {
        $canView = $this->permissions->hasTenantPermission($context, 'reports.view');

        return [
            'canExport' => $canView,
            'canOpenDispute' => $canView,
        ];
    }
}
