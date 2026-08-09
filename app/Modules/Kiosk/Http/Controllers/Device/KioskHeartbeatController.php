<?php

namespace App\Modules\Kiosk\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Modules\Kiosk\Application\Actions\BuildKioskAttendeeScanDetailsAction;
use App\Modules\Kiosk\Application\Actions\RecordKioskHeartbeatAction;
use App\Modules\Kiosk\Domain\Context\KioskSessionContextStore;
use App\Modules\Kiosk\Http\Requests\KioskHeartbeatRequest;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Events\Application\Support\EventWallClockDateTime;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\JsonResponse;
use Throwable;

final class KioskHeartbeatController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly KioskSessionContextStore $kioskContexts,
        private readonly BuildKioskAttendeeScanDetailsAction $attendeeDetails,
    ) {}

    public function store(KioskHeartbeatRequest $request, RecordKioskHeartbeatAction $action): JsonResponse
    {
        $context = $this->kioskContexts->current();

        $kiosk = Kiosk::query()
            ->where('tenant_id', $context->tenantId)
            ->findOrFail($context->kioskId);

        $action->execute(
            $kiosk,
            $request->string('printer_status', 'unknown')->toString(),
            $request->input('printer_reason_code'),
            $request->input('app_version'),
        );

        return $this->success([
            'status' => 'ok',
            'kiosk_id' => (string) $kiosk->id,
            'device_code' => (string) $kiosk->device_code,
            'device_name' => (string) $kiosk->device_name,
            'confirmation_required' => (bool) $kiosk->confirmation_required,
            'confirmed' => $context->confirmed,
            'event' => $this->eventPayload($context->tenantId, $context->eventId),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function eventPayload(string $tenantId, string $eventId): ?array
    {
        try {
            /** @var Event|null $event */
            $event = Event::query()
                ->where('tenant_id', $tenantId)
                ->with([
                    'venues',
                    'images' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
                    'agendaItems',
                ])
                ->find($eventId);

            if ($event === null) {
                return null;
            }

            $payload = $this->attendeeDetails->eventPayload($event);
            $timezone = (string) ($event->timezone ?: 'UTC');

            $days = $event->agendaItems
                ->map(function ($item) use ($timezone): ?array {
                    $date = $item->agenda_date?->toDateString()
                        ?? EventWallClockDateTime::toDateString($item->start_at, $timezone);

                    if ($date === null) {
                        return null;
                    }

                    return [
                        'date' => $date,
                        'title' => (string) ($item->title_en ?: $item->title_ar ?: 'Session'),
                        'title_ar' => (string) ($item->title_ar ?: ''),
                        'start_at' => EventWallClockDateTime::toIso8601($item->start_at, $timezone),
                        'end_at' => EventWallClockDateTime::toIso8601($item->end_at, $timezone),
                        'speaker' => $item->speaker ? (string) $item->speaker : null,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            if ($days === [] && $event->start_at !== null && $event->end_at !== null) {
                $cursor = EventWallClockDateTime::asEventLocal($event->start_at, $timezone)?->startOfDay();
                $end = EventWallClockDateTime::asEventLocal($event->end_at, $timezone)?->startOfDay();
                while ($cursor !== null && $end !== null && $cursor->lte($end)) {
                    $days[] = [
                        'date' => $cursor->toDateString(),
                        'title' => 'Event day',
                        'title_ar' => '',
                        'start_at' => null,
                        'end_at' => null,
                        'speaker' => null,
                    ];
                    $cursor = $cursor->addDay();
                }
            }

            $payload['days'] = $days;

            return $payload;
        } catch (Throwable) {
            return null;
        }
    }
}
