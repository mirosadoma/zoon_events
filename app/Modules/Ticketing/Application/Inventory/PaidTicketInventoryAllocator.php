<?php

namespace App\Modules\Ticketing\Application\Inventory;

use App\Modules\Ticketing\Application\Pricing\PriceTierEvaluator;
use App\Modules\Ticketing\Contracts\PaidTicketAllocator;
use App\Modules\Ticketing\Contracts\TicketPriceReader;
use App\Modules\Ticketing\Domain\PaidTicketAllocation;
use App\Modules\Ticketing\Domain\ValueObjects\Money;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\InventoryHold;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\PriceTier;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketInventory;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Carbon\CarbonImmutable;

final readonly class PaidTicketInventoryAllocator implements PaidTicketAllocator, TicketPriceReader
{
    public function __construct(
        private InventoryService $inventory,
        private PriceTierEvaluator $pricing,
    ) {}

    public function price(string $tenantId, string $eventId, string $ticketTypeId): Money
    {
        $ticket = $this->ticket($tenantId, $eventId, $ticketTypeId);

        return new Money($ticket->base_price_minor, $ticket->currency);
    }

    public function reserve(string $tenantId, string $eventId, string $ticketTypeId): PaidTicketAllocation
    {
        $ticket = $this->ticket($tenantId, $eventId, $ticketTypeId);
        $inventory = TicketInventory::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('ticket_type_id', $ticketTypeId)
            ->firstOrFail();
        $tiers = PriceTier::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('ticket_type_id', $ticketTypeId)
            ->where('status', 'active')
            ->get();
        $quote = $this->pricing->evaluate(
            new Money($ticket->base_price_minor, $ticket->currency),
            $tiers,
            CarbonImmutable::now(),
            $inventory->remaining(),
        );
        $hold = $this->inventory->reserve(
            $tenantId,
            $eventId,
            $ticketTypeId,
            1,
            $quote,
            CarbonImmutable::now()->addMinutes((int) config('registration.hold_minutes')),
        );

        return new PaidTicketAllocation(
            $hold->id,
            $ticket->id,
            ['en' => $ticket->name_en, 'ar' => $ticket->name_ar],
            $quote->money->minor,
            $quote->money->currency,
            $quote->priceTierId,
        );
    }

    public function linkAndConvert(string $tenantId, string $holdId, string $orderId): void
    {
        InventoryHold::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->findOrFail($holdId)
            ->update(['order_id' => $orderId]);
        $this->inventory->convert($tenantId, $holdId);
    }

    private function ticket(string $tenantId, string $eventId, string $ticketTypeId): TicketType
    {
        return TicketType::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'active')
            ->findOrFail($ticketTypeId);
    }
}
