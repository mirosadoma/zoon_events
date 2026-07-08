<?php

namespace App\Modules\Ticketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\Ticketing\Application\Actions\CreatePriceTier;
use App\Modules\Ticketing\Application\Actions\UpdatePriceTier;
use App\Modules\Ticketing\Http\Requests\PriceTierWriteRequest;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\PriceTier;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;

final class OrganizerPriceTierController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly TenantContextStore $contexts) {}

    public function store(PriceTierWriteRequest $request, string $eventId, string $ticketTypeId, CreatePriceTier $action)
    {
        $ticket = TicketType::query()
            ->where('tenant_id', $this->contexts->current()->tenant->id)
            ->where('event_id', $eventId)
            ->findOrFail($ticketTypeId);
        $tier = $action->execute($this->contexts->current(), $ticket, $request->validated());

        return $this->success([
            'id' => $tier->id,
            'name' => $tier->name,
            'price_minor' => $tier->price_minor,
            'currency' => $tier->currency,
            'starts_at' => $tier->starts_at?->toIso8601String(),
            'ends_at' => $tier->ends_at?->toIso8601String(),
            'remaining_at_most' => $tier->remaining_at_most,
            'priority' => $tier->priority,
            'status' => $tier->status,
        ], 201);
    }

    public function update(PriceTierWriteRequest $request, string $eventId, string $ticketTypeId, string $priceTierId, UpdatePriceTier $action)
    {
        $ticket = TicketType::query()
            ->where('tenant_id', $this->contexts->current()->tenant->id)
            ->where('event_id', $eventId)
            ->findOrFail($ticketTypeId);
        $tier = PriceTier::query()
            ->where('tenant_id', $this->contexts->current()->tenant->id)
            ->where('event_id', $eventId)
            ->where('ticket_type_id', $ticket->id)
            ->findOrFail($priceTierId);
        $tier = $action->execute($this->contexts->current(), $ticket, $tier, $request->validated());

        return $this->success([
            'id' => $tier->id,
            'name' => $tier->name,
            'price_minor' => $tier->price_minor,
            'currency' => $tier->currency,
            'starts_at' => $tier->starts_at?->toIso8601String(),
            'ends_at' => $tier->ends_at?->toIso8601String(),
            'remaining_at_most' => $tier->remaining_at_most,
            'priority' => $tier->priority,
            'status' => $tier->status,
        ]);
    }
}
