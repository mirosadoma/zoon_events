<?php

namespace App\Modules\Events\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Application\Actions\ArchiveEvent;
use App\Modules\Events\Application\Actions\CancelEvent;
use App\Modules\Events\Application\Actions\CreateEvent;
use App\Modules\Events\Application\Actions\PublishEvent;
use App\Modules\Events\Application\Actions\ReopenEvent;
use App\Modules\Events\Application\Actions\SyncEventMedia;
use App\Modules\Events\Application\Actions\UpdateEvent;
use App\Modules\Events\Http\Requests\EventWriteRequest;
use App\Modules\Events\Http\Resources\EventResource;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;

final class OrganizerEventController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly TenantContextStore $contexts) {}

    public function index()
    {
        $events = Event::query()->where('tenant_id', $this->contexts->current()->tenant->id)->orderByDesc('created_at')->limit(100)->get();

        return $this->success(EventResource::collection($events)->resolve());
    }

    public function store(EventWriteRequest $request, CreateEvent $action, SyncEventMedia $media)
    {
        $context = $this->contexts->current();
        $event = $action->execute($context, $request->attributesForAction());
        $event = $media->execute($context, $event, $request);

        return $this->success((new EventResource($event))->resolve(), 201);
    }

    public function show(string $eventId)
    {
        return $this->success((new EventResource($this->event($eventId)))->resolve());
    }

    public function update(EventWriteRequest $request, string $eventId, UpdateEvent $action, SyncEventMedia $media)
    {
        $context = $this->contexts->current();
        $event = $this->event($eventId);
        $event = $action->execute($context, $event, $request->attributesForAction());
        $event = $media->execute($context, $event, $request);

        return $this->success((new EventResource($event))->resolve());
    }

    public function publish(string $eventId, PublishEvent $action)
    {
        $event = $action->execute($this->contexts->current(), $this->event($eventId));

        return $this->success((new EventResource($event))->resolve());
    }

    public function cancel(Request $request, string $eventId, CancelEvent $action)
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $event = $action->execute($this->contexts->current(), $this->event($eventId), $validated['reason']);

        return $this->success((new EventResource($event))->resolve());
    }

    public function reopen(Request $request, string $eventId, ReopenEvent $action)
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $event = $action->execute($this->contexts->current(), $this->event($eventId), $validated['reason']);

        return $this->success((new EventResource($event))->resolve());
    }

    public function archive(Request $request, string $eventId, ArchiveEvent $action)
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $event = $action->execute($this->contexts->current(), $this->event($eventId), $validated['reason']);

        return $this->success((new EventResource($event))->resolve());
    }

    private function event(string $id): Event
    {
        return Event::query()->where('tenant_id', $this->contexts->current()->tenant->id)->findOrFail($id);
    }
}
