<?php

namespace App\Console\Commands;

use App\Modules\Payments\Application\Jobs\ReconcilePaymentAttemptJob;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAttempt;
use Illuminate\Console\Command;

final class ReconcilePayments extends Command
{
    protected $signature = 'zonetec:payments:reconcile {--limit=200}';

    protected $description = 'Queue due payment attempts for authoritative reconciliation';

    public function handle(): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $count = 0;
        PaymentAttempt::query()
            ->whereIn('status', ['pending', 'unknown'])
            ->where('next_reconcile_at', '<=', now())
            ->orderBy('next_reconcile_at')
            ->limit($limit)
            ->get(['id'])
            ->each(function (PaymentAttempt $attempt) use (&$count): void {
                ReconcilePaymentAttemptJob::dispatch($attempt->id);
                $count++;
            });
        $this->components->info("Queued {$count} payment attempts.");

        return self::SUCCESS;
    }
}
