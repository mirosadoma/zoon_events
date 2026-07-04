<?php

namespace App\Modules\Ticketing\Application\Actions;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Shared\Http\Problems\Phase1Problem;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Ticketing\Domain\Events\TicketTypeChanged;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketInventory;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Illuminate\Support\Facades\DB;

final readonly class SaveTicketType
{
    public function __construct(private AuditWriter $audit) {}

    /** @param array<string,mixed> $attributes */
    public function create(TenantContext $context, string $eventId, array $attributes): TicketType
    {
        return DB::transaction(function () use ($context, $eventId, $attributes): TicketType {
            $capacity = (int) $attributes['capacity'];
            unset($attributes['capacity']);
            $ticket = TicketType::query()->create([
                ...$attributes,
                'tenant_id' => $context->tenant->id,
                'event_id' => $eventId,
                'created_by_user_id' => $context->actor->id,
            ]);
            TicketInventory::query()->create([
                'tenant_id' => $context->tenant->id,
                'event_id' => $eventId,
                'ticket_type_id' => $ticket->id,
                'capacity' => $capacity,
            ]);
            $this->audit->writeTenant('ticket_type.created', 'succeeded', $context, targetType: 'ticket_type', targetId: $ticket->id);
            event(new TicketTypeChanged($context->tenant->id, $eventId, $ticket->id, 'created', $context->actor->id));

            return $ticket;
        });
    }

    /** @param array<string,mixed> $attributes */
    public function update(TenantContext $context, TicketType $ticket, array $attributes): TicketType
    {
        return DB::transaction(function () use ($context, $ticket, $attributes): TicketType {
            $capacity = (int) $attributes['capacity'];
            unset($attributes['capacity']);
            $ticket->fill($attributes)->save();
            $updated = TicketInventory::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $ticket->event_id)
                ->where('ticket_type_id', $ticket->id)
                ->whereRaw('held_quantity + sold_quantity <= ?', [$capacity])
                ->update(['capacity' => $capacity, 'updated_at' => now()]);
            if ($updated !== 1) {
                throw Phase1Problem::make('inventory_conflict');
            }
            $this->audit->writeTenant('ticket_type.updated', 'succeeded', $context, targetType: 'ticket_type', targetId: $ticket->id);
            event(new TicketTypeChanged($context->tenant->id, $ticket->event_id, $ticket->id, 'updated', $context->actor->id));

            return $ticket->refresh();
        });
    }
}
