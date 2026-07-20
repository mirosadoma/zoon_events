<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Events\Infrastructure\Persistence\Models\CategoryTemplatePrivilege;
use App\Modules\Events\Infrastructure\Persistence\Models\Privilege;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

final class PrivilegePageController extends Controller
{
    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
    ) {}

    public function index(): Response
    {
        $context = $this->authorizeTenant('privilege.view');

        $privileges = Privilege::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $context->tenant->id)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->map(fn (Privilege $privilege): array => $this->mapPrivilege($privilege))
            ->values()
            ->all();

        return Inertia::render('tenant/privileges/Index', [
            'tenantId' => (string) $context->tenant->id,
            'privileges' => $privileges,
            'canManage' => $this->permissions->hasTenantPermission($context, 'privilege.manage'),
        ]);
    }

    public function create(): Response
    {
        $context = $this->authorizeTenant('privilege.manage');

        return Inertia::render('tenant/privileges/Form', [
            'tenantId' => (string) $context->tenant->id,
            'privilege' => null,
        ]);
    }

    public function edit(string $locale, string $privilege_id): Response
    {
        unset($locale);

        $context = $this->authorizeTenant('privilege.manage');
        $privilege = Privilege::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($privilege_id);

        return Inertia::render('tenant/privileges/Form', [
            'tenantId' => (string) $context->tenant->id,
            'privilege' => $this->mapPrivilege($privilege),
        ]);
    }

    private function authorizeTenant(string $permission): TenantContext
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $context = $this->sessions->tenantContextFor($user);
        abort_unless($context instanceof TenantContext, 403);
        abort_unless($this->permissions->hasTenantPermission($context, $permission), 403);

        return $context;
    }

    /** @return array<string, mixed> */
    private function mapPrivilege(Privilege $privilege): array
    {
        return [
            'id' => (string) $privilege->id,
            'key' => $privilege->key,
            'label' => $privilege->label,
            'label_ar' => $privilege->label_ar,
            'effect' => $privilege->effect,
            'target_type' => $privilege->target_type,
            'target_id' => $privilege->target_id,
            'sort_order' => $privilege->sort_order,
            'in_use' => CategoryTemplatePrivilege::query()
                ->where('privilege_id', $privilege->id)
                ->exists(),
        ];
    }
}
