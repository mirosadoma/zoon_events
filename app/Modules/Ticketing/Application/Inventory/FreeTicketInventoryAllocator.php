<?php

namespace App\Modules\Ticketing\Application\Inventory;

use App\Modules\Shared\Http\Problems\Phase1Problem;
use App\Modules\Ticketing\Contracts\FreeTicketAllocator;
use App\Modules\Ticketing\Domain\FreeTicketAllocation;
use App\Modules\Ticketing\Domain\ValueObjects\Money;
use App\Modules\Ticketing\Domain\ValueObjects\PriceQuote;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\InventoryHold;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Carbon\CarbonImmutable;

final readonly class FreeTicketInventoryAllocator implements FreeTicketAllocator
{
    public function __construct(private InventoryService $inventory) {}

    public function reserve(string $tenantId, string $eventId, string $ticketTypeId): FreeTicketAllocation
    {
        $ticket = TicketType::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'active')
            ->findOrFail($ticketTypeId);
        if ($ticket->base_price_minor !== 0) {
            throw Phase1Problem::make('payment_action_required');
        }
        $hold = $this->inventory->reserve(
            $tenantId,
            $eventId,
            $ticket->id,
            1,
            new PriceQuote(new Money(0, $ticket->currency), null, CarbonImmutable::now()),
            CarbonImmutable::now()->addMinutes((int) config('registration.hold_minutes')),
        );

        return new FreeTicketAllocation(
            $hold->id,
            $ticket->id,
            ['en' => $ticket->name_en, 'ar' => $ticket->name_ar],
            $ticket->currency,
        );
    }

    public function linkAndConvert(string $tenantId, string $holdId, string $orderId): void
    {
        InventoryHold::query()->where('tenant_id', $tenantId)->where('status', 'active')->findOrFail($holdId)->update(['order_id' => $orderId]);
        $this->inventory->convert($tenantId, $holdId);
    }
}
