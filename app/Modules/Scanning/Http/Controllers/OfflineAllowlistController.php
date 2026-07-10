<?php

namespace App\Modules\Scanning\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authorization\Policies\Phase2\Phase2Policy;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Scanning\Application\Actions\GenerateOfflineAllowlistAction;
use App\Modules\Scanning\Http\Resources\OfflineAllowlistResource;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OfflineAllowlistController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly Phase2Policy $policy,
    ) {}

    public function show(Request $request, string $eventId, GenerateOfflineAllowlistAction $action): JsonResponse
    {
        $user = $request->user();
        if ($user === null || ! $this->policy->allows($user, 'submitScan')) {
            abort(403);
        }

        $tenantId = $this->contexts->current()->tenant->id;
        Event::query()->where('tenant_id', $tenantId)->findOrFail($eventId);

        $windowMinutes = null;
        if ($request->filled('window_minutes')) {
            $windowMinutes = max(1, min(1440, (int) $request->query('window_minutes')));
        }

        return $this->success((new OfflineAllowlistResource($action->execute($tenantId, $eventId, $windowMinutes)))->resolve());
    }
}
