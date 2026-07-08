<?php

namespace App\Modules\IdentityVerification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\IdentityVerification\Application\Actions\UpsertIdentityRequirementAction;
use App\Modules\IdentityVerification\Http\Requests\IdentityRequirementWriteRequest;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerificationRequirement;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RequirementsController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly TenantContextStore $contexts) {}

    public function index(Request $request, string $eventId): JsonResponse
    {
        $context = $this->contexts->current();
        $this->assertScopedEvent($eventId);

        $rows = IdentityVerificationRequirement::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $eventId)
            ->orderByRaw('ticket_type_id IS NULL DESC')
            ->orderBy('ticket_type_id')
            ->get()
            ->map(fn (IdentityVerificationRequirement $row): array => $this->toRow($row))
            ->values()
            ->all();

        return $this->success($rows);
    }

    public function update(
        IdentityRequirementWriteRequest $request,
        string $eventId,
        UpsertIdentityRequirementAction $action,
    ): JsonResponse {
        $context = $this->contexts->current();
        $this->assertScopedEvent($eventId);

        $payload = $request->validated();
        if (($payload['ticket_type_id'] ?? null) !== null) {
            TicketType::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $eventId)
                ->findOrFail($payload['ticket_type_id']);
        }

        $saved = $action->execute($context, $eventId, $payload);

        return $this->success($this->toRow($saved));
    }

    private function assertScopedEvent(string $eventId): void
    {
        Event::query()
            ->where('tenant_id', $this->contexts->current()->tenant->id)
            ->findOrFail($eventId);
    }

    /** @return array{id:string,event_id:string,ticket_type_id:string|null,level:string,face_fallback_enabled:bool} */
    private function toRow(IdentityVerificationRequirement $requirement): array
    {
        return [
            'id' => (string) $requirement->id,
            'event_id' => (string) $requirement->event_id,
            'ticket_type_id' => $requirement->ticket_type_id !== null ? (string) $requirement->ticket_type_id : null,
            'level' => (string) $requirement->level,
            'face_fallback_enabled' => (bool) $requirement->face_fallback_enabled,
        ];
    }
}
