<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\ViewModels\Marketplace\TenantVenueDetailViewModel;
use App\Modules\AdminConsole\ViewModels\Marketplace\TenantVenueIndexViewModel;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TenantVenuePageController extends Controller
{
    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly TenantVenueIndexViewModel $indexViewModel,
        private readonly TenantVenueDetailViewModel $detailViewModel,
    ) {}

    public function index(Request $request): Response
    {
        $context = $this->authorizeVenueManage();

        return Inertia::render(
            'tenant/venues/Index',
            $this->indexViewModel->index(
                $context->tenant->id,
                $request->only(['status', 'country_id', 'city_id', 'publication_readiness', 'cursor']),
                $this->venuePermissions($context),
            ),
        );
    }

    public function create(): Response
    {
        $context = $this->authorizeVenueManage();

        return Inertia::render('tenant/venues/Show', [
            'tenantId' => $context->tenant->id,
            'venue' => null,
            'actions' => $this->venuePermissions($context),
        ]);
    }

    public function show(string $venuePublicId): Response
    {
        $context = $this->authorizeVenueManage();

        return Inertia::render(
            'tenant/venues/Show',
            $this->detailViewModel->show(
                $context->tenant->id,
                $venuePublicId,
                $this->venuePermissions($context),
            ),
        );
    }

    private function authorizeVenueManage(): TenantContext
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $context = $this->sessions->tenantContextFor($user);
        abort_unless($context instanceof TenantContext, 403);
        abort_unless($this->permissions->hasTenantPermission($context, 'venue.manage'), 403);

        return $context;
    }

    /**
     * @return array<string, bool>
     */
    private function venuePermissions(TenantContext $context): array
    {
        $canManage = $this->permissions->hasTenantPermission($context, 'venue.manage');

        return [
            'canCreate' => $canManage,
            'canUpdate' => $canManage,
            'canArchive' => $canManage,
            'canChangeStatus' => $canManage,
            'canManageAssets' => $canManage,
            'canPublish' => $canManage,
            'canWithdraw' => $canManage,
        ];
    }
}
