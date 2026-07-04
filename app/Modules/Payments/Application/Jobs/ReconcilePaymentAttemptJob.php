<?php

namespace App\Modules\Payments\Application\Jobs;

use App\Modules\Payments\Application\Actions\ReconcilePaymentAttempt;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAttempt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ReconcilePaymentAttemptJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $attemptId) {}

    public function handle(ReconcilePaymentAttempt $reconcile): void
    {
        $attempt = PaymentAttempt::query()->findOrFail($this->attemptId);
        if (in_array($attempt->status, ['pending', 'unknown'], true)) {
            $reconcile->execute($attempt);
        }
    }
}
