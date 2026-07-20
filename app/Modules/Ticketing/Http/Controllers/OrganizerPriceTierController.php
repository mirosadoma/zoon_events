<?php

namespace App\Modules\Ticketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Application\Support\EventWallClockDateTime;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
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
        $timezone = $this->eventTimezone($eventId);
        $tier = $action->execute($this->contexts->current(), $ticket, $this->normalizedAttributes($request->validated(), $timezone));

        return $this->success($this->mapTier($tier, $timezone), 201);
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
        $timezone = $this->eventTimezone($eventId);
        $tier = $action->execute($this->contexts->current(), $ticket, $tier, $this->normalizedAttributes($request->validated(), $timezone));

        return $this->success($this->mapTier($tier, $timezone));
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function normalizedAttributes(array $attributes, string $timezone): array
    {
        if (array_key_exists('starts_at', $attributes)) {
            $attributes['starts_at'] = EventWallClockDateTime::parseToAppStorage(
                isset($attributes['starts_at']) ? (string) $attributes['starts_at'] : null,
                $timezone,
            )?->toDateTimeString();
        }
        if (array_key_exists('ends_at', $attributes)) {
            $attributes['ends_at'] = EventWallClockDateTime::parseToAppStorage(
                isset($attributes['ends_at']) ? (string) $attributes['ends_at'] : null,
                $timezone,
            )?->toDateTimeString();
        }

        return $attributes;
    }

    private function eventTimezone(string $eventId): string
    {
        $timezone = Event::query()->whereKey($eventId)->value('timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }

    /** @return array<string, mixed> */
    private function mapTier(PriceTier $tier, string $timezone): array
    {
        return [
            'id' => $tier->id,
            'name' => $tier->name,
            'price_minor' => $tier->price_minor,
            'currency' => $tier->currency,
            'starts_at' => EventWallClockDateTime::toInput($tier->starts_at, $timezone),
            'ends_at' => EventWallClockDateTime::toInput($tier->ends_at, $timezone),
            'remaining_at_most' => $tier->remaining_at_most,
            'priority' => $tier->priority,
            'status' => $tier->status,
        ];
    }
}
