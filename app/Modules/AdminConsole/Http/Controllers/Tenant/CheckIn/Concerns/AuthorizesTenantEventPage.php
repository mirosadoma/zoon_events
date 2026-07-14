<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn\Concerns;

use App\Models\User;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\Http\Controllers\Concerns\ResolvesRouteParam;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Tenancy\Domain\Context\TenantContext;

trait AuthorizesTenantEventPage
{
    use ResolvesRouteParam;

    private function authorizeTenantEvent(
        SessionContextBuilder $sessions,
        PermissionEvaluator $permissions,
        ?string $eventId,
        string $permission,
    ): array {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $context = $sessions->tenantContextFor($user);
        abort_unless($context instanceof TenantContext, 403);
        abort_unless($permissions->hasTenantPermission($context, $permission), 403);

        $resolved = $this->routeParamOrNull('event_id') ?? $eventId;

        abort_unless(is_string($resolved) && $resolved !== '', 404);

        $event = Event::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($resolved);

        return [$context, $event];
    }
}
