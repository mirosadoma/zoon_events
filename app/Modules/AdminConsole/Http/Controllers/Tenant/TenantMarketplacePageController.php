<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\ViewModels\Marketplace\TenantMarketplaceCatalogViewModel;
use App\Modules\AdminConsole\ViewModels\Marketplace\TenantRentalViewModel;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TenantMarketplacePageController extends Controller
{
    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly TenantMarketplaceCatalogViewModel $catalogViewModel,
        private readonly TenantRentalViewModel $rentalViewModel,
    ) {}

    public function index(Request $request): Response
    {
        $context = $this->authorizeCatalog();

        return Inertia::render(
            'tenant/marketplace/Index',
            $this->catalogViewModel->index(
                $context->tenant->id,
                $request->only([
                    'venue_public_id',
                    'country_id',
                    'city_id',
                    'asset_type',
                    'capability',
                    'minimum_capacity',
                    'currency',
                    'starts_at',
                    'ends_at',
                    'cursor',
                ]),
                $this->catalogPermissions($context),
            ),
        );
    }

    public function rentalsIndex(Request $request): Response
    {
        $context = $this->authorizeRentals();

        return Inertia::render(
            'tenant/marketplace/rentals/Index',
            $this->rentalViewModel->index(
                $context->tenant->id,
                $request->only([
                    'role',
                    'status',
                    'venue_public_id',
                    'event_id',
                    'dispute_status',
                    'from',
                    'to',
                    'cursor',
                ]),
                $this->rentalPermissions($context),
            ),
        );
    }

    public function rentalShow(string $rentalPublicId): Response
    {
        $context = $this->authorizeRentals();

        return Inertia::render(
            'tenant/marketplace/rentals/Show',
            $this->rentalViewModel->show(
                $context->tenant->id,
                $rentalPublicId,
                $this->rentalPermissions($context),
            ),
        );
    }

    private function authorizeCatalog(): TenantContext
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $context = $this->sessions->tenantContextFor($user);
        abort_unless($context instanceof TenantContext, 403);
        abort_unless($this->permissions->hasTenantPermission($context, 'marketplace.manage'), 403);

        return $context;
    }

    private function authorizeRentals(): TenantContext
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $context = $this->sessions->tenantContextFor($user);
        abort_unless($context instanceof TenantContext, 403);

        $allowed = $this->permissions->hasTenantPermission($context, 'marketplace.manage')
            || $this->permissions->hasTenantPermission($context, 'rentals.approve')
            || $this->permissions->hasTenantPermission($context, 'reports.view');

        abort_unless($allowed, 403);

        return $context;
    }

    /**
     * @return array<string, bool>
     */
    private function catalogPermissions(TenantContext $context): array
    {
        $canManage = $this->permissions->hasTenantPermission($context, 'marketplace.manage');

        return [
            'canQuote' => $canManage,
            'canRequest' => $canManage,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function rentalPermissions(TenantContext $context): array
    {
        $canManage = $this->permissions->hasTenantPermission($context, 'marketplace.manage');
        $canApprove = $this->permissions->hasTenantPermission($context, 'rentals.approve');

        return [
            'canApprove' => $canApprove,
            'canReject' => $canApprove,
            'canRevoke' => $canApprove,
            'canCancel' => $canManage,
            'canViewDelegation' => $canManage || $canApprove,
        ];
    }
}
