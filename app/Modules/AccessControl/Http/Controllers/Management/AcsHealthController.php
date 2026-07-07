<?php

namespace App\Modules\AccessControl\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Modules\AccessControl\Application\Queries\AcsHealthQuery;
use App\Modules\Authorization\Policies\Phase4\Phase4Policy;
use App\Modules\Shared\Http\Problems\Phase4Problem;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AcsHealthController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly Phase4Policy $policy,
        private readonly AcsHealthQuery $query,
        private readonly AcsScopedEventGuard $scopedEvent,
    ) {}

    public function index(Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $this->policy->allows($user, 'viewAcsHealth')) {
            throw Phase4Problem::make('acs_events_not_permitted');
        }

        $context = $this->contexts->current();

        return $this->success($this->query->summary($context->tenant->id, $eventId));
    }
}
