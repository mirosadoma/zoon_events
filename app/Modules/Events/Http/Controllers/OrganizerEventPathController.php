<?php

namespace App\Modules\Events\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Application\Actions\SyncEventPaths;
use App\Modules\Events\Application\Support\EventPathPresenter;
use App\Modules\Events\Contracts\EventScope;
use App\Modules\Events\Http\Requests\PathSyncRequest;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventPath;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class OrganizerEventPathController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly EventScope $events,
    ) {}

    public function sync(PathSyncRequest $request, string $eventId, SyncEventPaths $action)
    {
        $tenantId = (string) $this->contexts->current()->tenant->id;
        abort_unless($this->events->exists($tenantId, $eventId), 404);

        $event = Event::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($eventId)
            ->firstOrFail();

        try {
            $action->execute(
                $tenantId,
                $event,
                (int) $request->validated('venue_id'),
                $request->validated('paths'),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'paths' => [$exception->getMessage()],
            ]);
        }

        $saved = EventPath::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $event->id)
            ->where('venue_id', (int) $request->validated('venue_id'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (EventPath $path): array => EventPathPresenter::toArray($path))
            ->values()
            ->all();

        return $this->success(['paths' => $saved]);
    }
}
