<?php

namespace App\Modules\Kiosk\Application\Actions;

use App\Modules\AdminConsole\Application\PersonalDataReader;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgePrintJob;
use App\Modules\Credentials\Application\Presentation\CredentialPresentationToken;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Orders\Infrastructure\Persistence\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class ListKioskAttendeesAction
{
    public function __construct(
        private PersonalDataReader $personalData,
        private CredentialPresentationToken $presentationTokens,
    ) {}

    /**
     * @return array{
     *   event_id: string,
     *   synced_at: string,
     *   attendees: list<array<string, mixed>>
     * }
     */
    public function execute(string $tenantId, string $eventId): array
    {
        $attendees = Attendee::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereNotIn('registration_status', ['cancelled', 'anonymized'])
            ->orderBy('id')
            ->get();

        $credentials = Credential::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereIn('attendee_id', $attendees->pluck('id')->all())
            ->whereNull('superseded_by_id')
            ->where('status', '!=', 'revoked')
            ->orderByDesc('issued_at')
            ->get()
            ->groupBy(fn (Credential $credential): string => (string) $credential->attendee_id);

        $ticketTypes = TicketType::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->get()
            ->keyBy(fn (TicketType $ticket): string => (string) $ticket->id);

        $venues = EventVenue::query()
            ->where('event_id', $eventId)
            ->get()
            ->keyBy(fn (EventVenue $venue): string => (string) $venue->id);

        $printCounts = BadgePrintJob::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereIn('attendee_id', $attendees->pluck('id')->all())
            ->where('status', 'printed')
            ->selectRaw('attendee_id, COALESCE(SUM(print_count), 0) as total_prints, MAX(printed_at) as last_printed_at')
            ->groupBy('attendee_id')
            ->get()
            ->keyBy(fn (object $row): string => (string) $row->attendee_id);

        $orderReferences = $this->orderReferencesByAttendee(
            $tenantId,
            $eventId,
            $attendees->pluck('id')->map(fn ($id): string => (string) $id)->all(),
        );

        $rows = $attendees->map(function (Attendee $attendee) use ($credentials, $ticketTypes, $venues, $printCounts, $orderReferences): array {
            /** @var Collection<int, Credential> $attendeeCredentials */
            $attendeeCredentials = $credentials->get((string) $attendee->id, collect());
            /** @var Credential|null $credential */
            $credential = $attendeeCredentials->first();

            $qrPayload = null;
            if ($credential !== null) {
                try {
                    $qrPayload = $this->presentationTokens->resolve($credential);
                } catch (\Throwable) {
                    $qrPayload = null;
                }
            }

            $ticketTypeId = (string) ($credential?->ticket_type_id ?? $attendee->ticket_type_id ?? '');
            $ticket = $ticketTypeId !== '' ? $ticketTypes->get($ticketTypeId) : null;
            $venueId = $attendee->event_venue_id !== null ? (string) $attendee->event_venue_id : null;
            $venue = $venueId !== null ? $venues->get($venueId) : null;
            $print = $printCounts->get((string) $attendee->id);

            return [
                'attendee_id' => (string) $attendee->id,
                'credential_id' => $credential !== null ? (string) $credential->id : null,
                'display_name' => $this->personalData->attendeeDisplayName($attendee) ?: 'Attendee',
                'email' => $this->personalData->attendeeEmail($attendee),
                'ticket_type_id' => $ticketTypeId !== '' ? $ticketTypeId : null,
                'ticket_type_label' => $ticket
                    ? (string) ($ticket->name_en ?: $ticket->name_ar ?: $ticket->code ?: '')
                    : null,
                'venue_id' => $venueId,
                'venue_name' => $venue
                    ? (string) ($venue->name_en ?: $venue->name_ar ?: 'Venue')
                    : null,
                'checkin_status' => (string) ($attendee->checkin_status ?: 'not_checked_in'),
                'first_checked_in_at' => $attendee->first_checked_in_at?->toIso8601String(),
                'print_count' => (int) ($print->total_prints ?? 0),
                'last_printed_at' => isset($print->last_printed_at)
                    ? CarbonImmutable::parse((string) $print->last_printed_at)->toIso8601String()
                    : null,
                'qr_payload' => $qrPayload,
                'order_reference' => $orderReferences[(string) $attendee->id] ?? null,
                'credential_status' => $credential !== null ? (string) $credential->status : null,
                'credential_expires_at' => $credential?->expires_at?->toIso8601String(),
            ];
        })->values()->all();

        return [
            'event_id' => $eventId,
            'synced_at' => CarbonImmutable::now()->toIso8601String(),
            'attendees' => $rows,
        ];
    }

    /**
     * @param  list<string>  $attendeeIds
     * @return array<string, string>
     */
    private function orderReferencesByAttendee(string $tenantId, string $eventId, array $attendeeIds): array
    {
        if ($attendeeIds === []) {
            return [];
        }

        $items = OrderItem::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('attendee_id', $attendeeIds)
            ->whereNotNull('order_id')
            ->get(['attendee_id', 'order_id']);

        if ($items->isEmpty()) {
            return [];
        }

        $orders = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->whereIn('id', $items->pluck('order_id')->unique()->all())
            ->get(['id', 'public_reference'])
            ->keyBy(fn (Order $order): string => (string) $order->id);

        $map = [];
        foreach ($items as $item) {
            $attendeeId = (string) $item->attendee_id;
            if (isset($map[$attendeeId])) {
                continue;
            }
            $order = $orders->get((string) $item->order_id);
            $reference = is_string($order?->public_reference) ? trim($order->public_reference) : '';
            if ($reference !== '') {
                $map[$attendeeId] = $reference;
            }
        }

        return $map;
    }
}
