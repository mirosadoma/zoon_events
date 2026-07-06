<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn;

use App\Http\Controllers\Controller;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(private readonly TenantContextStore $contexts) {}

    public function show(string $eventId): Response
    {
        $tenant = $this->contexts->current()->tenant;
        Event::query()->where('tenant_id', $tenant->id)->findOrFail($eventId);

        return Inertia::render('tenant/checkin/Dashboard', [
            'eventId' => $eventId,
            'tenantId' => $tenant->id,
        ]);
    }
}
