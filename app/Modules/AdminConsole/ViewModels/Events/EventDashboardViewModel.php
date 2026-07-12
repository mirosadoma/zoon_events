<?php

namespace App\Modules\AdminConsole\ViewModels\Events;

use App\Modules\Events\Application\Publication\PublicationReadiness;
use App\Modules\Events\Application\Support\PublicRegistrationUrlBuilder;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\PriceTier;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketInventory;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class EventDashboardViewModel
{
    public function __construct(
        private PublicationReadiness $readiness,
        private PublicRegistrationUrlBuilder $registrationUrls,
    ) {}

    /**
     * @param  Collection<int, Event>  $events
     * @return array{events:list<array<string,mixed>>}
     */
    public function index(Collection $events): array
    {
        return [
            'events' => $events->map(fn (Event $event): array => $this->eventRow($event))->values()->all(),
        ];
    }

    /**
     * @return array{event:array<string,mixed>,tabs:list<array<string,string>>}
     */
    public function detail(Event $event): array
    {
        return [
            'event' => $this->eventRow($event),
            'tabs' => [
                ['label' => 'Agenda', 'href' => "/tenant/events/{$event->id}/agenda"],
                ['label' => 'Registration form', 'href' => "/tenant/events/{$event->id}/registration-form"],
                ['label' => 'Identity requirements', 'href' => "/tenant/events/{$event->id}/identity"],
                ['label' => 'Ticket types', 'href' => "/tenant/events/{$event->id}/ticket-types"],
                ['label' => 'Price tiers', 'href' => "/tenant/events/{$event->id}/price-tiers"],
                ['label' => 'Orders', 'href' => "/tenant/events/{$event->id}/orders"],
                ['label' => 'Attendees', 'href' => "/tenant/events/{$event->id}/attendees"],
                ['label' => 'Credentials', 'href' => "/tenant/events/{$event->id}/credentials"],
                ['label' => 'Wallet passes', 'href' => "/tenant/events/{$event->id}/wallet-passes"],
                ['label' => 'Check-in dashboard', 'href' => "/tenant/events/{$event->id}/check-in-dashboard"],
                ['label' => 'Scanner', 'href' => "/tenant/events/{$event->id}/scanner"],
                ['label' => 'Scan events', 'href' => "/tenant/events/{$event->id}/scan-events"],
                ['label' => 'Kiosks', 'href' => "/tenant/events/{$event->id}/kiosks"],
                ['label' => 'Badge templates', 'href' => "/tenant/events/{$event->id}/badge-templates"],
                ['label' => 'Badge print jobs', 'href' => "/tenant/events/{$event->id}/badge-print-jobs"],
                ['label' => 'Manual desk', 'href' => "/tenant/events/{$event->id}/manual-desk"],
                ['label' => 'ACS', 'href' => "/tenant/events/{$event->id}/acs"],
                ['label' => 'Reports', 'href' => "/tenant/events/{$event->id}/reports"],
            ],
        ];
    }

    /**
     * @param  Collection<int, TicketType>  $tickets
     * @param  Collection<string, TicketInventory>  $inventory
     * @return array{tenantId:string,event:array<string,mixed>,tickets:list<array<string,mixed>>}
     */
    public function ticketing(Event $event, string $tenantId, Collection $tickets, Collection $inventory): array
    {
        return [
            'tenantId' => $tenantId,
            'event' => $this->eventRow($event),
            'tickets' => $tickets->map(function (TicketType $ticket) use ($inventory): array {
                $stock = $inventory->get($ticket->id);

                return [
                    'id' => $ticket->id,
                    'code' => $ticket->code,
                    'name' => ['en' => $ticket->name_en, 'ar' => $ticket->name_ar],
                    'description' => ['en' => $ticket->description_en ?? '', 'ar' => $ticket->description_ar ?? ''],
                    'attendee_type' => $ticket->attendee_type,
                    'price_minor' => $ticket->base_price_minor,
                    'currency' => $ticket->currency,
                    'capacity' => $stock?->capacity ?? 0,
                    'remaining_quantity' => $stock?->remaining() ?? 0,
                    'sale_starts_at' => $ticket->sale_starts_at?->toIso8601String(),
                    'sale_ends_at' => $ticket->sale_ends_at?->toIso8601String(),
                    'status' => $ticket->status,
                    'state' => $this->availability($ticket, $stock),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, PriceTier>  $tiers
     * @param  Collection<int, TicketType>  $ticketTypes
     * @return array{tenantId:string,event:array<string,mixed>,ticketTypes:list<array<string,mixed>>,priceTiers:list<array<string,mixed>>}
     */
    public function priceTiers(Event $event, string $tenantId, Collection $tiers, Collection $ticketTypes): array
    {
        return [
            'tenantId' => $tenantId,
            'event' => $this->eventRow($event),
            'ticketTypes' => $ticketTypes->map(fn (TicketType $ticket): array => [
                'id' => $ticket->id,
                'code' => $ticket->code,
                'name' => ['en' => $ticket->name_en, 'ar' => $ticket->name_ar],
                'currency' => $ticket->currency,
            ])->values()->all(),
            'priceTiers' => $tiers->map(fn (PriceTier $tier): array => [
                'id' => $tier->id,
                'name' => $tier->name,
                'ticket_type_id' => $tier->ticket_type_id,
                'price_minor' => $tier->price_minor,
                'currency' => $tier->currency,
                'starts_at' => $tier->starts_at?->toIso8601String(),
                'ends_at' => $tier->ends_at?->toIso8601String(),
                'remaining_at_most' => $tier->remaining_at_most,
                'priority' => $tier->priority,
                'status' => $tier->status,
                'is_active_now' => $tier->status === 'active'
                    && ($tier->starts_at === null || $tier->starts_at->isPast())
                    && ($tier->ends_at === null || $tier->ends_at->isFuture()),
            ])->values()->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function eventRow(Event $event): array
    {
        return [
            'id' => $event->id,
            'slug' => $event->slug,
            'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            'status' => $event->status,
            'tier' => $event->tier,
            'timezone' => $event->timezone,
            'start_at' => $event->start_at?->toIso8601String(),
            'end_at' => $event->end_at?->toIso8601String(),
            'capacity' => $event->capacity,
            'readiness' => $this->readiness->missing([
                ...$event->only(['name_en', 'name_ar', 'timezone', 'start_at', 'end_at', 'registration_opens_at', 'registration_closes_at', 'active_form_version_id', 'main_image_path']),
                'active_ticket_types' => DB::table('ticket_types')
                    ->where('tenant_id', $event->tenant_id)
                    ->where('event_id', $event->id)
                    ->where('status', 'active')
                    ->count(),
                'branding_active' => $event->branding()->where('status', 'active')->exists(),
            ]),
            'registration_url' => $this->registrationUrls->forEvent($event),
        ];
    }

    private function availability(TicketType $ticket, ?TicketInventory $inventory): string
    {
        if ($ticket->status === 'paused') {
            return 'paused';
        }

        if ($inventory !== null && $inventory->remaining() <= 0) {
            return 'sold_out';
        }

        return 'available';
    }
}
