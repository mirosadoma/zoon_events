<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\Application\Support\InertiaListPaginator;
use App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn\Concerns\AuthorizesTenantEventPage;
use App\Modules\AdminConsole\ViewModels\Wallet\WalletPassDetailViewModel;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class WalletPassesController extends Controller
{
    use AuthorizesTenantEventPage;

    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly WalletPassDetailViewModel $viewModel,
    ) {}

    public function index(Request $request, string $eventId): Response
    {
        [$context, $event] = $this->authorizeTenantEvent(
            $this->sessions,
            $this->permissions,
            $eventId,
            'wallet.pass.view',
        );

        $query = WalletPass::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->latest('last_pushed_at')
            ->orderByDesc('id');

        $result = InertiaListPaginator::paginate($query, $request);

        return Inertia::render(
            'tenant/checkin/WalletPasses',
            $this->viewModel->index($event, $result['items'], $result['pagination']),
        );
    }

    public function show(string $eventId, string $passId): Response
    {
        [$context, $event] = $this->authorizeTenantEvent(
            $this->sessions,
            $this->permissions,
            $eventId,
            'wallet.pass.view',
        );

        $pass = WalletPass::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->findOrFail($this->routeParamOrNull('pass_id') ?? $passId);

        return Inertia::render('tenant/wallet/Detail', $this->viewModel->detail($event, $pass));
    }
}
