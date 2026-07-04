<?php

namespace App\Modules\Ticketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Contracts\EventScope;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\Ticketing\Application\Actions\SaveTicketType;
use App\Modules\Ticketing\Http\Requests\TicketTypeWriteRequest;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;

final class OrganizerTicketTypeController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly EventScope $events,
    ) {}

    public function index(string $eventId)
    {
        $this->event($eventId);
        $tickets = TicketType::query()->where('tenant_id', $this->contexts->current()->tenant->id)->where('event_id', $eventId)->get();

        return $this->success($tickets->map($this->map(...))->all());
    }

    public function store(TicketTypeWriteRequest $request, string $eventId, SaveTicketType $action)
    {
        $this->event($eventId);
        $ticket = $action->create($this->contexts->current(), $eventId, $request->attributesForAction());

        return $this->success($this->map($ticket), 201);
    }

    public function update(TicketTypeWriteRequest $request, string $eventId, string $ticketTypeId, SaveTicketType $action)
    {
        $this->event($eventId);
        $ticket = TicketType::query()
            ->where('tenant_id', $this->contexts->current()->tenant->id)
            ->where('event_id', $eventId)
            ->findOrFail($ticketTypeId);
        $ticket = $action->update($this->contexts->current(), $ticket, $request->attributesForAction());

        return $this->success($this->map($ticket));
    }

    private function event(string $id): void
    {
        abort_unless($this->events->exists($this->contexts->current()->tenant->id, $id), 404);
    }

    private function map(TicketType $ticket): array
    {
        return [
            'id' => $ticket->id,
            'code' => $ticket->code,
            'name' => ['en' => $ticket->name_en, 'ar' => $ticket->name_ar],
            'price_minor' => $ticket->base_price_minor,
            'currency' => $ticket->currency,
            'availability' => match ($ticket->status) {
                'paused' => 'paused',
                'sold_out' => 'sold_out',
                default => now()->isBefore($ticket->sale_starts_at) ? 'not_started' : (now()->isAfter($ticket->sale_ends_at) ? 'ended' : 'available'),
            },
            'status' => $ticket->status,
        ];
    }
}
