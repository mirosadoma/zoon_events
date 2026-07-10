<?php

namespace App\Modules\Events\Application\Queries;

use App\Modules\Events\Domain\Context\PublicEventContext;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;

final class GetPublicEvent
{
    public function execute(PublicEventContext $context): Event
    {
        return Event::query()
            ->with('branding')
            ->where('tenant_id', $context->tenantId)
            ->findOrFail($context->eventId);
    }
}
