<?php

namespace App\Modules\Ticketing\Application\Queries;

use App\Modules\Ticketing\Contracts\PublicTicketCatalog;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;

final class DatabasePublicTicketCatalog implements PublicTicketCatalog
{
    public function forEvent(string $tenantId, string $eventId): array
    {
        return TicketType::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'active')
            ->orderBy('base_price_minor')
            ->get()
            ->map(fn (TicketType $ticket): array => [
                'id' => $ticket->id,
                'code' => $ticket->code,
                'name' => ['en' => $ticket->name_en, 'ar' => $ticket->name_ar],
                'price_minor' => $ticket->base_price_minor,
                'currency' => $ticket->currency,
                'status' => $ticket->status,
            ])->all();
    }
}
