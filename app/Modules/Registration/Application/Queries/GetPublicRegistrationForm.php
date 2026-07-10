<?php

namespace App\Modules\Registration\Application\Queries;

use App\Modules\Events\Domain\Context\PublicEventContext;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use App\Modules\Ticketing\Contracts\PublicTicketCatalog;

final class GetPublicRegistrationForm
{
    public function __construct(private readonly PublicTicketCatalog $tickets) {}

    /** @return array{form:RegistrationFormVersion,tickets:list<array<string,mixed>>} */
    public function execute(PublicEventContext $context): array
    {
        $form = RegistrationFormVersion::query()
            ->where('tenant_id', $context->tenantId)
            ->where('event_id', $context->eventId)
            ->where('status', 'published')
            ->latest('version')
            ->firstOrFail();
        $tickets = $this->tickets->forEvent($context->tenantId, $context->eventId);

        return compact('form', 'tickets');
    }
}
