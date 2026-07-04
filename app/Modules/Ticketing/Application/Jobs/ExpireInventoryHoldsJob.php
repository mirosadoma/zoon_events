<?php

namespace App\Modules\Ticketing\Application\Jobs;

use App\Modules\Ticketing\Application\Inventory\InventoryService;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\InventoryHold;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ExpireInventoryHoldsJob implements ShouldQueue
{
    use Queueable;

    public function handle(InventoryService $inventory): void
    {
        InventoryHold::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(500)
            ->get(['id', 'tenant_id'])
            ->each(fn (InventoryHold $hold) => $inventory->expire($hold->tenant_id, $hold->id));
    }
}
