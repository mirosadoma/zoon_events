<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn\Concerns\AuthorizesTenantEventPage;
use App\Modules\AdminConsole\ViewModels\Wallet\WalletPassDetailViewModel;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\WalletPasses\Infrastructure\Persistence\Models\WalletPass;
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

    public function index(string $eventId): Response
    {
        [$context, $event] = $this->authorizeTenantEvent(
            $this->sessions,
            $this->permissions,
            $eventId,
            'wallet.pass.view',
        );

        $passes = WalletPass::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->latest('last_pushed_at')
            ->limit(200)
            ->get();

        return Inertia::render('tenant/checkin/WalletPasses', $this->viewModel->index($event, $passes));
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
            ->findOrFail($passId);

        return Inertia::render('tenant/wallet/Detail', $this->viewModel->detail($event, $pass));
    }
}
