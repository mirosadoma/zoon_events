<?php

namespace App\Modules\AdminConsole\ViewModels\ManualDesk;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Illuminate\Support\Collection;

final readonly class ManualDeskViewModel
{
    /**
     * @param  Collection<int, TicketType>  $ticketTypes
     * @return array{event: array<string, mixed>, tenantId: string, ticketTypes: list<array<string, mixed>>}
     */
    public function make(Event $event, string $tenantId, Collection $ticketTypes): array
    {
        return [
            'event' => $this->eventRow($event),
            'tenantId' => $tenantId,
            'ticketTypes' => $ticketTypes->map(fn (TicketType $ticket): array => [
                'id' => $ticket->id,
                'code' => $ticket->code,
                'name' => ['en' => $ticket->name_en, 'ar' => $ticket->name_ar],
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function eventRow(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
        ];
    }
}
