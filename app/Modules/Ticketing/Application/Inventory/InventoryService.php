<?php

namespace App\Modules\Ticketing\Application\Inventory;

use App\Modules\Operations\Application\Telemetry\TicketInventoryTelemetry;
use App\Modules\Shared\Http\Problems\Phase1Problem;
use App\Modules\Ticketing\Contracts\TicketHoldReleaser;
use App\Modules\Ticketing\Domain\Events\InventoryStateChanged;
use App\Modules\Ticketing\Domain\ValueObjects\PriceQuote;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\InventoryHold;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketInventory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class InventoryService implements TicketHoldReleaser
{
    public function __construct(private readonly TicketInventoryTelemetry $telemetry) {}

    public function reserve(
        string $tenantId,
        string $eventId,
        string $ticketTypeId,
        int $quantity,
        PriceQuote $quote,
        CarbonImmutable $expiresAt,
        ?string $orderId = null,
    ): InventoryHold {
        $started = microtime(true);
        $hold = DB::transaction(function () use ($tenantId, $eventId, $ticketTypeId, $quantity, $quote, $expiresAt, $orderId): InventoryHold {
            $inventory = $this->lockedInventory($tenantId, $eventId, $ticketTypeId);
            if ($quantity < 1 || $inventory->remaining() < $quantity) {
                throw Phase1Problem::make('ticket_unavailable');
            }
            $inventory->forceFill([
                'held_quantity' => $inventory->held_quantity + $quantity,
                'version' => $inventory->version + 1,
            ])->save();

            return InventoryHold::query()->create([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'ticket_type_id' => $ticketTypeId,
                'order_id' => $orderId,
                'quantity' => $quantity,
                'quoted_price_minor' => $quote->money->minor,
                'currency' => $quote->money->currency,
                'price_tier_id' => $quote->priceTierId,
                'status' => 'active',
                'expires_at' => $expiresAt,
            ]);
        }, 3);
        $this->telemetry->transition('held', (int) round((microtime(true) - $started) * 1000));
        event(new InventoryStateChanged($tenantId, $eventId, $ticketTypeId, $hold->id, 'held'));

        return $hold;
    }

    public function convert(string $tenantId, string $holdId): InventoryHold
    {
        $hold = DB::transaction(function () use ($tenantId, $holdId): InventoryHold {
            $hold = InventoryHold::query()->where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($holdId);
            if ($hold->status === 'converted') {
                return $hold;
            }
            if ($hold->status !== 'active' || $hold->expires_at->isPast()) {
                throw Phase1Problem::make('inventory_conflict');
            }
            $inventory = $this->lockedInventory($tenantId, $hold->event_id, $hold->ticket_type_id);
            $inventory->forceFill([
                'held_quantity' => $inventory->held_quantity - $hold->quantity,
                'sold_quantity' => $inventory->sold_quantity + $hold->quantity,
                'version' => $inventory->version + 1,
            ])->save();
            $hold->forceFill(['status' => 'converted'])->save();

            return $hold->refresh();
        }, 3);
        $this->telemetry->transition('converted', 0);
        event(new InventoryStateChanged($tenantId, $hold->event_id, $hold->ticket_type_id, $hold->id, 'converted'));

        return $hold;
    }

    public function release(string $tenantId, string $holdId, string $reason = 'released'): InventoryHold
    {
        return $this->terminalRelease($tenantId, $holdId, 'released', $reason);
    }

    public function expire(string $tenantId, string $holdId): InventoryHold
    {
        return $this->terminalRelease($tenantId, $holdId, 'expired', 'hold_expired');
    }

    private function terminalRelease(string $tenantId, string $holdId, string $status, string $reason): InventoryHold
    {
        $hold = DB::transaction(function () use ($tenantId, $holdId, $status, $reason): InventoryHold {
            $hold = InventoryHold::query()->where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($holdId);
            if ($hold->status !== 'active') {
                return $hold;
            }
            $inventory = $this->lockedInventory($tenantId, $hold->event_id, $hold->ticket_type_id);
            $inventory->forceFill([
                'held_quantity' => $inventory->held_quantity - $hold->quantity,
                'version' => $inventory->version + 1,
            ])->save();
            $hold->forceFill(['status' => $status, 'released_reason_code' => $reason])->save();

            return $hold->refresh();
        }, 3);
        $this->telemetry->transition($status, 0);
        event(new InventoryStateChanged($tenantId, $hold->event_id, $hold->ticket_type_id, $hold->id, $status));

        return $hold;
    }

    private function lockedInventory(string $tenantId, string $eventId, string $ticketTypeId): TicketInventory
    {
        return TicketInventory::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('ticket_type_id', $ticketTypeId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
