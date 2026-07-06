<?php

namespace App\Modules\Scanning\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authorization\Policies\Phase2\Phase2Policy;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Scanning\Application\Queries\GetCheckInSummaryQuery;
use App\Modules\Scanning\Http\Resources\CheckInSummaryResource;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CheckInSummaryController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly Phase2Policy $policy,
    ) {}

    public function show(Request $request, string $eventId, GetCheckInSummaryQuery $query): JsonResponse
    {
        $user = $request->user();
        if ($user === null || ! $this->policy->allows($user, 'viewCheckInDashboard')) {
            abort(403);
        }

        $tenantId = $this->contexts->current()->tenant->id;
        Event::query()->where('tenant_id', $tenantId)->findOrFail($eventId);

        return $this->success((new CheckInSummaryResource($query->handle($tenantId, $eventId)))->resolve());
    }
}
