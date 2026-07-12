<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Exceptions\FoundationException;
use App\Models\User;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Closure;
use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function __construct(
        private readonly TenantContextStore $store,
        private readonly Registrar $router,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();

        if (! $actor instanceof User || ! $actor->isActive()) {
            throw FoundationException::unauthenticated();
        }

        $tenantId = (string) $request->headers->get(config('tenancy.tenant_header', 'X-Tenant-ID'), '');

        if ($tenantId === '') {
            throw FoundationException::forbidden('tenant_context_required', 'A trusted tenant context is required.');
        }

        $membership = TenantMembership::query()
            ->with('tenant')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $actor->id)
            ->first();

        if (! $membership instanceof TenantMembership || ! $membership->tenant instanceof Tenant) {
            throw FoundationException::notFound();
        }

        if (
            $membership->status !== LifecycleStatus::Active
            || $membership->tenant->status !== LifecycleStatus::Active
        ) {
            throw FoundationException::forbidden('tenant_context_invalid', 'The tenant context is inactive.');
        }

        $this->store->bind($membership->tenant, $membership, $actor);
        $request->attributes->set('trusted_tenant_id', $membership->tenant->id);

        $route = $request->route();

        if ($route !== null) {
            try {
                $this->router->substituteBindings($route);
                $this->router->substituteImplicitBindings($route);
            } catch (ModelNotFoundException $exception) {
                if ($route->getMissing()) {
                    return $route->getMissing()($request, $exception);
                }

                throw $exception;
            }
        }

        return $next($request);
    }
}
