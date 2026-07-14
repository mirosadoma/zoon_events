<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\Events\Concerns;

use App\Modules\AdminConsole\Http\Controllers\Concerns\ResolvesRouteParam;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Tenancy\Domain\Context\TenantContext;

trait ResolvesTenantEventFromRoute
{
    use ResolvesRouteParam;

    private function event(TenantContext $context, ?string $eventId = null): Event
    {
        $resolved = $this->routeParamOrNull('event_id') ?? $eventId;

        abort_unless(is_string($resolved) && $resolved !== '', 404);

        return Event::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($resolved);
    }
}
