<?php

namespace App\Modules\Payments\Application\Jobs;

use App\Modules\Payments\Infrastructure\Persistence\Models\Refund;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ReconcileRefundJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $refundId) {}

    public function handle(): void
    {
        $refund = Refund::query()->findOrFail($this->refundId);
        if ($refund->status === 'unknown') {
            $refund->forceFill(['last_reconciled_at' => now()])->save();
        }
    }
}
