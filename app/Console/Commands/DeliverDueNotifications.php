<?php

namespace App\Console\Commands;

use App\Modules\Notifications\Application\Jobs\DeliverNotificationJob;
use App\Modules\Notifications\Infrastructure\Persistence\Models\Notification;
use Illuminate\Console\Command;

final class DeliverDueNotifications extends Command
{
    protected $signature = 'zonetec:notifications:deliver-due {--limit=100}';

    protected $description = 'Queue due notification intents for bounded delivery attempts';

    public function handle(): int
    {
        $limit = min(500, max(1, (int) $this->option('limit')));
        $count = 0;
        Notification::query()
            ->where(function ($query): void {
                $query->where(function ($due): void {
                    $due->whereIn('status', ['pending', 'temporary_failure'])
                        ->where(fn ($attempt) => $attempt
                            ->whereNull('next_attempt_at')
                            ->orWhere('next_attempt_at', '<=', now()));
                })->orWhere(function ($stale): void {
                    $stale->where('status', 'processing')
                        ->where('updated_at', '<=', now()->subMinutes(10));
                });
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->each(function (string $id) use (&$count): void {
                DeliverNotificationJob::dispatch($id);
                $count++;
            });

        $this->components->info("Queued {$count} notification(s).");

        return self::SUCCESS;
    }
}
