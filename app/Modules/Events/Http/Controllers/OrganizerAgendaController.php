<?php

namespace App\Modules\Events\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Application\Actions\SyncEventAgendaItems;
use App\Modules\Events\Contracts\EventScope;
use App\Modules\Events\Http\Requests\AgendaSyncRequest;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventAgendaItem;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;

final class OrganizerAgendaController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly EventScope $events,
    ) {}

    public function sync(AgendaSyncRequest $request, string $eventId, SyncEventAgendaItems $action)
    {
        $tenantId = (string) $this->contexts->current()->tenant->id;
        abort_unless($this->events->exists($tenantId, $eventId), 404);

        $event = Event::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($eventId)
            ->firstOrFail();

        $items = $request->validated('items');

        $action->execute($tenantId, $event, $items);

        $saved = EventAgendaItem::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->orderBy('sort_order')
            ->orderBy('start_at')
            ->get()
            ->map(fn (EventAgendaItem $item): array => $this->mapItem($item))
            ->values()
            ->all();

        return $this->success(['items' => $saved]);
    }

    private function mapItem(EventAgendaItem $item): array
    {
        return [
            'id' => (string) $item->id,
            'title' => ['en' => $item->title_en, 'ar' => $item->title_ar],
            'start_at' => $item->start_at?->toIso8601String(),
            'end_at' => $item->end_at?->toIso8601String(),
            'sort_order' => $item->sort_order,
        ];
    }
}
