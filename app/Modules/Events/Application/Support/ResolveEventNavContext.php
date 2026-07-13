<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\Events\Domain\EventRegistrationProfile;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;

final class ResolveEventNavContext
{
    public function __construct(private readonly TenantContextStore $contexts) {}

    /** @return array{event_id:string,capabilities:array<string,bool>}|null */
    public function forRequest(Request $request): ?array
    {
        $eventId = $request->route('event_id');

        if (! is_string($eventId) || $eventId === '' || $eventId === 'create') {
            return null;
        }

        $context = $this->contexts->currentOrNull();

        if ($context === null) {
            return null;
        }

        $event = Event::query()
            ->where('tenant_id', $context->tenant->id)
            ->find($eventId, ['id', 'tenant_id', 'tier', 'registration_mode']);

        if (! $event instanceof Event) {
            return null;
        }

        return [
            'event_id' => (string) $event->id,
            'capabilities' => EventRegistrationProfile::capabilities($event),
        ];
    }
}
