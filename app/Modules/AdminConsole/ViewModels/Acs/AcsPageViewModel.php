<?php

namespace App\Modules\AdminConsole\ViewModels\Acs;

use App\Modules\AccessControl\Application\Queries\AcsHealthQuery;
use App\Modules\AccessControl\Application\Queries\GateEventsQuery;
use App\Modules\AccessControl\Http\Resources\AccessEventResource;
use App\Modules\AccessControl\Http\Resources\AcsLaneResource;
use App\Modules\AccessControl\Http\Resources\AcsRuleResource;
use App\Modules\AccessControl\Http\Resources\AcsZoneResource;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsAuthorizationRule;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Illuminate\Support\Collection;

final readonly class AcsPageViewModel
{
    public function __construct(
        private AcsHealthQuery $healthQuery,
        private GateEventsQuery $gateEventsQuery,
    ) {}

    /**
     * @return array{
     *     event: array<string, mixed>,
     *     tenantId: string,
     *     overview: array<string, mixed>
     * }
     */
    public function overview(Event $event, string $tenantId): array
    {
        $zonesCount = AcsZone::query()->where('tenant_id', $tenantId)->where('event_id', $event->id)->count();
        $lanesCount = AcsLane::query()->where('tenant_id', $tenantId)->where('event_id', $event->id)->count();
        $rulesCount = AcsAuthorizationRule::query()->where('tenant_id', $tenantId)->where('event_id', $event->id)->count();
        $health = $this->healthQuery->summary($tenantId, $event->id);
        $recentEvents = $this->gateEventsQuery->list($tenantId, $event->id, null, 5);

        return [
            'event' => $this->eventRow($event),
            'tenantId' => $tenantId,
            'overview' => [
                'zones_total' => $zonesCount,
                'lanes_total' => $lanesCount,
                'rules_total' => $rulesCount,
                'integration_status' => $health['integration_status'],
                'active_emergency' => $health['active_emergency'],
                'gates_offline' => collect($health['lanes'])->where('health_status', 'offline')->count(),
                'latest_gate_events' => array_map(
                    fn (AccessEvent $accessEvent): array => (new AccessEventResource($accessEvent))->resolve(),
                    $recentEvents,
                ),
            ],
        ];
    }

    /**
     * @param  Collection<int, AcsZone>  $zones
     * @return array{event: array<string, mixed>, tenantId: string, zones: list<array<string, mixed>>}
     */
    public function zones(Event $event, string $tenantId, Collection $zones): array
    {
        return [
            'event' => $this->eventRow($event),
            'tenantId' => $tenantId,
            'zones' => $zones->map(fn (AcsZone $zone): array => (new AcsZoneResource($zone))->resolve())->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, AcsZone>  $zones
     * @param  Collection<int, AcsLane>  $lanes
     * @return array{event: array<string, mixed>, tenantId: string, zones: list<array<string, mixed>>, lanes: list<array<string, mixed>>}
     */
    public function lanes(Event $event, string $tenantId, Collection $zones, Collection $lanes): array
    {
        return [
            'event' => $this->eventRow($event),
            'tenantId' => $tenantId,
            'zones' => $zones->map(fn (AcsZone $zone): array => (new AcsZoneResource($zone))->resolve())->values()->all(),
            'lanes' => $lanes->map(fn (AcsLane $lane): array => (new AcsLaneResource($lane))->resolve())->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, AcsZone>  $zones
     * @param  Collection<int, AcsLane>  $lanes
     * @param  Collection<int, AcsAuthorizationRule>  $rules
     * @param  Collection<int, TicketType>  $ticketTypes
     * @return array{event: array<string, mixed>, tenantId: string, zones: list<array<string, mixed>>, lanes: list<array<string, mixed>>, rules: list<array<string, mixed>>, ticketTypes: list<array<string, mixed>>}
     */
    public function rules(
        Event $event,
        string $tenantId,
        Collection $zones,
        Collection $lanes,
        Collection $rules,
        Collection $ticketTypes,
    ): array {
        return [
            'event' => $this->eventRow($event),
            'tenantId' => $tenantId,
            'zones' => $zones->map(fn (AcsZone $zone): array => (new AcsZoneResource($zone))->resolve())->values()->all(),
            'lanes' => $lanes->map(fn (AcsLane $lane): array => (new AcsLaneResource($lane))->resolve())->values()->all(),
            'rules' => $rules->map(fn (AcsAuthorizationRule $rule): array => (new AcsRuleResource($rule))->resolve())->values()->all(),
            'ticketTypes' => $ticketTypes->map(fn (TicketType $ticket): array => [
                'id' => $ticket->id,
                'code' => $ticket->code,
                'name' => ['en' => $ticket->name_en, 'ar' => $ticket->name_ar],
            ])->values()->all(),
        ];
    }

    /**
     * @param  list<AccessEvent>  $events
     * @return array{event: array<string, mixed>, tenantId: string, accessEvents: list<array<string, mixed>>}
     */
    public function accessLogs(Event $event, string $tenantId, array $events): array
    {
        return [
            'event' => $this->eventRow($event),
            'tenantId' => $tenantId,
            'accessEvents' => array_map(
                fn (AccessEvent $accessEvent): array => (new AccessEventResource($accessEvent))->resolve(),
                $events,
            ),
        ];
    }

    /**
     * @return array{event: array<string, mixed>, tenantId: string, health: array<string, mixed>}
     */
    public function gateHealth(Event $event, string $tenantId): array
    {
        return [
            'event' => $this->eventRow($event),
            'tenantId' => $tenantId,
            'health' => $this->healthQuery->summary($tenantId, $event->id),
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
