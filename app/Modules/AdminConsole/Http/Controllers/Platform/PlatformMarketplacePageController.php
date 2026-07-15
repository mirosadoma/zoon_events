<?php

namespace App\Modules\AdminConsole\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AdminConsole\ViewModels\Marketplace\PlatformMarketplaceViewModel;
use App\Modules\Authorization\Application\PermissionEvaluator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformMarketplacePageController extends Controller
{
    public function __construct(
        private readonly PermissionEvaluator $permissions,
        private readonly PlatformMarketplaceViewModel $viewModel,
    ) {}

    public function index(Request $request): Response
    {
        $user = $this->authorizePlatformView();

        return Inertia::render(
            'platform/marketplace/Index',
            $this->viewModel->index(
                $request->only([
                    'status',
                    'owner_tenant_id',
                    'organizer_tenant_id',
                    'venue_public_id',
                    'event_id',
                    'from',
                    'to',
                    'cursor',
                ]),
                $this->platformPermissions($user),
            ),
        );
    }

    public function disputeShow(string $disputePublicId): Response
    {
        $user = $this->authorizeDisputeManage();

        return Inertia::render(
            'platform/marketplace/disputes/Show',
            $this->viewModel->disputeShow(
                $disputePublicId,
                $this->platformPermissions($user),
            ),
        );
    }

    private function authorizePlatformView(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);
        abort_unless($this->permissions->hasPlatformPermission($user, 'platform.marketplace.view'), 403);

        return $user;
    }

    private function authorizeDisputeManage(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);
        abort_unless($this->permissions->hasPlatformPermission($user, 'platform.marketplace.disputes.manage'), 403);

        return $user;
    }

    /**
     * @return array<string, bool>
     */
    private function platformPermissions(User $user): array
    {
        $canView = $this->permissions->hasPlatformPermission($user, 'platform.marketplace.view');
        $canManageDisputes = $this->permissions->hasPlatformPermission($user, 'platform.marketplace.disputes.manage');

        return [
            'canView' => $canView,
            'canManageDisputes' => $canManageDisputes,
            'canStartReview' => $canManageDisputes,
            'canAddNote' => $canManageDisputes,
            'canResolve' => $canManageDisputes,
            'canReject' => $canManageDisputes,
        ];
    }
}
