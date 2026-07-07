<?php

namespace App\Modules\Kiosk\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Modules\Authorization\Policies\Phase3\Phase3Policy;
use App\Modules\Kiosk\Application\Actions\PairKioskAction;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class KioskPairingController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly Phase3Policy $policy,
    ) {}

    public function store(Request $request, string $eventId, string $kioskId, PairKioskAction $action): JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $this->policy->allows($user, 'manageKiosk')) {
            abort(403);
        }

        $context = $this->contexts->current();

        $kiosk = Kiosk::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $eventId)
            ->findOrFail($kioskId);

        $result = $action->execute($kiosk);

        return $this->success([
            'session_secret' => $result['secret'],
            'expires_at' => $result['expiresAt']->format('Y-m-d\TH:i:s\Z'),
        ], 201);
    }
}
