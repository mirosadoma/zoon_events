<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\Events\Concerns;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Tenancy\Domain\Context\TenantContext;

trait ResolvesTenantEventFromRoute
{
    private function event(TenantContext $context, ?string $eventId = null): Event
    {
        $resolved = request()->route('event_id');

        if (! is_string($resolved) || $resolved === '') {
            $resolved = $eventId;
        }

        abort_unless(is_string($resolved) && $resolved !== '', 404);

        return Event::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($resolved);
    }
}
