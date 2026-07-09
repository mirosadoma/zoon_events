<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AdminConsole\Application\MembershipVisibility;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\Http\Controllers\Tenant\Admin\Concerns\AuthorizesTenantAdminPage;
use App\Modules\AdminConsole\ViewModels\Admin\AuditLogsViewModel;
use App\Modules\AdminConsole\ViewModels\Admin\RolesViewModel;
use App\Modules\AdminConsole\ViewModels\Admin\TenantSettingsViewModel;
use App\Modules\AdminConsole\ViewModels\Admin\UsersViewModel;
use App\Modules\Audit\Application\Queries\SearchAuditLogs;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Authorization\Infrastructure\Persistence\Models\Permission;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantConfiguration;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AdminPageController extends Controller
{
    use AuthorizesTenantAdminPage;

    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly MembershipVisibility $membershipVisibility,
        private readonly UsersViewModel $users,
        private readonly RolesViewModel $roles,
        private readonly TenantSettingsViewModel $tenantSettings,
        private readonly AuditLogsViewModel $auditLogs,
        private readonly SearchAuditLogs $searchAuditLogs,
    ) {}

    public function users(Request $request): Response
    {
        $context = $this->authorizeTenantAdmin($this->sessions, $this->permissions, 'membership.view');
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $query = $this->membershipVisibility
            ->scopeVisibleMemberships(
                TenantMembership::query()->with('user'),
                $context,
                $user,
            );

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $memberships = $query->orderByDesc('created_at')->limit(200)->get();

        return Inertia::render('admin/Users', $this->users->index($context->tenant->id, $memberships, (int) $user->id));
    }

    public function roles(): Response
    {
        $context = $this->authorizeTenantAdmin($this->sessions, $this->permissions, 'role.view');
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $roles = TenantRole::query()
            ->withoutGlobalScopes()
            ->with('permissions')
            ->where('tenant_id', $context->tenant->id)
            ->where('is_system', false)
            ->where('created_by_user_id', $user->id)
            ->orderBy('name_en')
            ->orderBy('name')
            ->get();

        $availablePermissions = Permission::query()
            ->where('scope', 'tenant')
            ->orderBy('module')
            ->orderBy('key')
            ->get(['key', 'module'])
            ->map(fn (Permission $permission): array => [
                'key' => $permission->key,
                'module' => $permission->module,
            ])
            ->values()
            ->all();

        return Inertia::render('admin/Roles', [
            ...$this->roles->index($context->tenant->id, $roles),
            'availablePermissions' => $availablePermissions,
        ]);
    }

    public function tenantSettings(): Response
    {
        $context = $this->authorizeTenantAdmin($this->sessions, $this->permissions, 'tenant.view');

        $configurations = TenantConfiguration::query()
            ->where('tenant_id', $context->tenant->id)
            ->orderBy('key')
            ->get();

        return Inertia::render('admin/TenantSettings', $this->tenantSettings->index(
            $context->tenant,
            $context->tenant->id,
            $configurations,
        ));
    }

    public function auditLogs(Request $request): Response
    {
        $context = $this->authorizeTenantAdmin($this->sessions, $this->permissions, 'audit.view');

        $filters = $request->only(['from', 'to', 'action', 'outcome', 'actor_id']);
        $page = $this->searchAuditLogs->tenant($context->tenant->id, $filters);

        return Inertia::render('admin/AuditLogs', $this->auditLogs->index(
            $context->tenant->id,
            [
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'action' => $filters['action'] ?? null,
                'outcome' => $filters['outcome'] ?? null,
                'actor_id' => $filters['actor_id'] ?? null,
            ],
            $page->items,
        ));
    }
}
