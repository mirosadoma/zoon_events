<?php

namespace App\Modules\AccessControl\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Modules\AccessControl\Application\Queries\GateEventsQuery;
use App\Modules\AccessControl\Http\Resources\AccessEventResource;
use App\Modules\Authorization\Policies\Phase4\Phase4Policy;
use App\Modules\Shared\Http\Problems\Phase4Problem;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GateEventsController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly Phase4Policy $policy,
        private readonly GateEventsQuery $query,
        private readonly AcsScopedEventGuard $scopedEvent,
    ) {}

    public function index(Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $this->policy->allows($user, 'viewGateEvents')) {
            throw Phase4Problem::make('acs_events_not_permitted');
        }

        $context = $this->contexts->current();
        $since = $request->filled('since') ? $request->date('since') : null;
        $limit = (int) $request->input('limit', 50);

        $events = $this->query->list($context->tenant->id, $eventId, $since, $limit);

        return $this->success(
            array_map(fn ($event) => (new AccessEventResource($event))->resolve(), $events),
        );
    }
}
