<?php

namespace App\Modules\AdminConsole\ViewModels\Events;

use App\Modules\Events\Application\Actions\UnpublishEvent;
use App\Modules\Events\Application\Publication\PublicationReadiness;
use App\Modules\Events\Application\Support\EventWallClockDateTime;
use App\Modules\Events\Application\Support\PublicRegistrationUrlBuilder;
use App\Modules\Events\Domain\EventRegistrationProfile;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerificationRequirement;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Ticketing\Contracts\ActiveTicketCounter;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\PriceTier;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketInventory;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Illuminate\Support\Collection;

final readonly class EventDashboardViewModel
{
    public function __construct(
        private PublicationReadiness $readiness,
        private PublicRegistrationUrlBuilder $registrationUrls,
        private ActiveTicketCounter $tickets,
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
     * @return array{
     *   event:array<string,mixed>,
     *   setupTabs:list<array{label:string,href:string,key:string,completed:bool}>,
     *   operationsTabs:list<array{label:string,href:string,key:string,completed:bool}>,
     *   eventCapabilities:array<string,bool>
     * }
     */
    public function detail(Event $event): array
    {
        $capabilities = EventRegistrationProfile::capabilities($event);
        $missing = $this->readiness->missingForEvent(
            $event,
            $this->tickets->countOrganizerTicketTypesForEvent($event->tenant_id, $event->id),
        );
        $setupProgress = $this->setupProgress($event, $missing, $capabilities);
        $tabs = $this->tabsFor($event, $capabilities, $setupProgress);

        return [
            'event' => [
                ...$this->eventRow($event),
                'setup_progress' => $setupProgress,
            ],
            'eventCapabilities' => $capabilities,
            'setupTabs' => $tabs['setupTabs'],
            'operationsTabs' => $tabs['operationsTabs'],
        ];
    }

    /**
     * @param  Collection<int, TicketType>  $tickets
     * @param  Collection<string, TicketInventory>  $inventory
     * @return array{tenantId:string,event:array<string,mixed>,tickets:list<array<string,mixed>>,eventCapabilities:array<string,bool>}
     */
    public function ticketing(Event $event, string $tenantId, Collection $tickets, Collection $inventory): array
    {
        return [
            'tenantId' => $tenantId,
            'event' => $this->eventRow($event),
            'eventCapabilities' => EventRegistrationProfile::capabilities($event),
            'tickets' => $tickets
                ->reject(fn (TicketType $ticket): bool => $ticket->code === EventRegistrationProfile::SYSTEM_REGISTRATION_TICKET_CODE)
                ->map(function (TicketType $ticket) use ($inventory): array {
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
                        'sale_starts_at' => EventWallClockDateTime::toInput($ticket->sale_starts_at, $event->timezone),
                        'sale_ends_at' => EventWallClockDateTime::toInput($ticket->sale_ends_at, $event->timezone),
                        'status' => $ticket->status,
                        'state' => $this->availability($ticket, $stock),
                    ];
                })->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, PriceTier>  $tiers
     * @param  Collection<int, TicketType>  $ticketTypes
     * @return array{tenantId:string,event:array<string,mixed>,ticketTypes:list<array<string,mixed>>,priceTiers:list<array<string,mixed>>,eventCapabilities:array<string,bool>}
     */
    public function priceTiers(Event $event, string $tenantId, Collection $tiers, Collection $ticketTypes): array
    {
        $organizerTickets = $ticketTypes->reject(
            fn (TicketType $ticket): bool => $ticket->code === EventRegistrationProfile::SYSTEM_REGISTRATION_TICKET_CODE,
        );

        return [
            'tenantId' => $tenantId,
            'event' => $this->eventRow($event),
            'eventCapabilities' => EventRegistrationProfile::capabilities($event),
            'ticketTypes' => $organizerTickets->map(fn (TicketType $ticket): array => [
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
                'starts_at' => EventWallClockDateTime::toInput($tier->starts_at, $event->timezone),
                'ends_at' => EventWallClockDateTime::toInput($tier->ends_at, $event->timezone),
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
    public function eventRow(Event $event): array
    {
        return [
            'id' => $event->id,
            'slug' => $event->slug,
            'code' => $event->code,
            'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            'status' => $event->status,
            'tier' => $event->tier,
            'event_type' => $event->event_type ?? 'seminar',
            'registration_mode' => $event->registration_mode ?? 'free_registration',
            'timezone' => $event->timezone,
            'start_at' => EventWallClockDateTime::toIso8601($event->start_at, $event->timezone),
            'end_at' => EventWallClockDateTime::toIso8601($event->end_at, $event->timezone),
            'capacity' => $event->capacity,
            'readiness' => $this->readiness->missingForEvent(
                $event,
                $this->tickets->countOrganizerTicketTypesForEvent($event->tenant_id, $event->id),
            ),
            'registration_url' => $this->registrationUrls->forEvent($event),
            'capabilities' => EventRegistrationProfile::capabilities($event),
            'can_unpublish' => UnpublishEvent::canUnpublish($event),
        ];
    }

    /**
     * @param  list<string>  $missing
     * @param  array{requires_ticketing:bool,requires_price_tiers:bool}  $capabilities
     * @return array{
     *   registration_form:bool,
     *   ticket_types:bool,
     *   price_tiers:bool,
     *   agenda:bool,
     *   categories:bool,
     *   badge_templates:bool,
     *   kiosks:bool,
     *   identity:bool,
     *   published:bool
     * }
     */
    private function setupProgress(Event $event, array $missing, array $capabilities): array
    {
        return [
            'registration_form' => ! in_array('active_form_version_id', $missing, true),
            'ticket_types' => ! $capabilities['requires_ticketing'] || ! in_array('active_ticket_type', $missing, true),
            'price_tiers' => ! $capabilities['requires_price_tiers']
                || PriceTier::query()
                    ->where('tenant_id', $event->tenant_id)
                    ->where('event_id', $event->id)
                    ->exists(),
            'agenda' => ! in_array('published_agenda', $missing, true),
            'categories' => ! in_array('event_categories', $missing, true),
            'badge_templates' => ! in_array('active_badge_template', $missing, true),
            'kiosks' => Kiosk::query()
                ->where('tenant_id', $event->tenant_id)
                ->where('event_id', $event->id)
                ->whereNull('retired_at')
                ->exists(),
            'identity' => IdentityVerificationRequirement::query()
                ->where('tenant_id', $event->tenant_id)
                ->where('event_id', $event->id)
                ->exists(),
            'published' => ! in_array($event->status, ['draft', 'configured'], true),
        ];
    }

    /**
     * @param  array{requires_ticketing:bool,requires_price_tiers:bool}  $capabilities
     * @param  array{
     *   registration_form:bool,
     *   ticket_types:bool,
     *   price_tiers:bool,
     *   agenda:bool,
     *   categories:bool,
     *   badge_templates:bool,
     *   kiosks:bool,
     *   identity:bool,
     *   published:bool
     * }  $setupProgress
     * @return array{
     *   setupTabs:list<array{label:string,href:string,key:string,completed:bool}>,
     *   operationsTabs:list<array{label:string,href:string,key:string,completed:bool}>
     * }
     */
    private function tabsFor(Event $event, array $capabilities, array $setupProgress): array
    {
        $base = "/tenant/events/{$event->id}";
        $setupTabs = [
            ['label' => 'Agenda', 'href' => "{$base}/agenda", 'key' => 'agenda', 'completed' => $setupProgress['agenda']],
            ['label' => 'Registration form', 'href' => "{$base}/registration-form", 'key' => 'registration_form', 'completed' => $setupProgress['registration_form']],
        ];

        if ($capabilities['requires_ticketing']) {
            $setupTabs[] = ['label' => 'Ticket types', 'href' => "{$base}/ticket-types", 'key' => 'ticket_types', 'completed' => $setupProgress['ticket_types']];
        }

        if ($capabilities['requires_price_tiers']) {
            $setupTabs[] = ['label' => 'Price tiers', 'href' => "{$base}/price-tiers", 'key' => 'price_tiers', 'completed' => $setupProgress['price_tiers']];
        }

        $setupTabs[] = ['label' => 'Categories', 'href' => "{$base}/categories", 'key' => 'categories', 'completed' => $setupProgress['categories']];
        $setupTabs[] = ['label' => 'Badge templates', 'href' => "{$base}/badge-templates", 'key' => 'badge_templates', 'completed' => $setupProgress['badge_templates']];
        $setupTabs[] = ['label' => 'Kiosks', 'href' => "{$base}/kiosks", 'key' => 'kiosks', 'completed' => $setupProgress['kiosks']];

        return [
            'setupTabs' => $setupTabs,
            'operationsTabs' => [
                ['label' => 'Identity requirements', 'href' => "{$base}/identity", 'key' => 'identity', 'completed' => false],
                ['label' => 'Orders', 'href' => "{$base}/orders", 'key' => 'orders', 'completed' => false],
                ['label' => 'Attendees', 'href' => "{$base}/attendees", 'key' => 'attendees', 'completed' => false],
                ['label' => 'Credentials', 'href' => "{$base}/credentials", 'key' => 'credentials', 'completed' => false],
                ['label' => 'Wallet passes', 'href' => "{$base}/wallet-passes", 'key' => 'wallet_passes', 'completed' => false],
                ['label' => 'Check-in dashboard', 'href' => "{$base}/check-in-dashboard", 'key' => 'check_in_dashboard', 'completed' => false],
                ['label' => 'Scanner', 'href' => "{$base}/scanner", 'key' => 'scanner', 'completed' => false],
                ['label' => 'Scan events', 'href' => "{$base}/scan-events", 'key' => 'scan_events', 'completed' => false],
                ['label' => 'Badge print jobs', 'href' => "{$base}/badge-print-jobs", 'key' => 'badge_print_jobs', 'completed' => false],
                ['label' => 'Manual desk', 'href' => "{$base}/manual-desk", 'key' => 'manual_desk', 'completed' => false],
                ['label' => 'ACS', 'href' => "{$base}/acs", 'key' => 'acs', 'completed' => false],
                ['label' => 'Reports', 'href' => "{$base}/reports", 'key' => 'reports', 'completed' => false],
            ],
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
