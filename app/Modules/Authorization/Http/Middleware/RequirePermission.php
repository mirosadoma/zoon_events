<?php

namespace App\Modules\Authorization\Http\Middleware;

use App\Exceptions\FoundationException;
use App\Models\User;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function __construct(
        private readonly PermissionEvaluator $permissions,
        private readonly TenantContextStore $tenantContextStore,
        private readonly AuditWriter $audit,
    ) {}

    public function handle(Request $request, Closure $next, string $permission, string $scope = 'platform'): Response
    {
        $actor = $request->user();

        if (! $actor instanceof User || ! $actor->isActive()) {
            throw FoundationException::unauthenticated();
        }

        $allowed = $scope === 'tenant'
            ? ($this->tenantContextStore->current() !== null && $this->permissions->hasTenantPermission($this->tenantContextStore->current(), $permission))
            : $this->permissions->hasPlatformPermission($actor, $permission);

        if (! $allowed) {
            if ($scope === 'tenant' && $this->tenantContextStore->current() !== null) {
                $this->audit->writeTenant('authorization.denied', 'denied', $this->tenantContextStore->current(), 'missing_permission', metadata: ['permission' => $permission]);
            } else {
                $this->audit->writePlatform('authorization.denied', 'denied', $actor, 'missing_permission', metadata: ['permission' => $permission]);
            }

            throw FoundationException::forbidden();
        }

        return $next($request);
    }
}
