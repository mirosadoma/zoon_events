<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\Admin\Concerns;

use App\Models\User;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Tenancy\Domain\Context\TenantContext;

trait AuthorizesTenantAdminPage
{
    private function authorizeTenantAdmin(
        SessionContextBuilder $sessions,
        PermissionEvaluator $permissions,
        string $permission,
    ): TenantContext {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $context = $sessions->tenantContextFor($user);
        abort_unless($context instanceof TenantContext, 403);
        abort_unless($permissions->hasTenantPermission($context, $permission), 403);

        return $context;
    }
}
