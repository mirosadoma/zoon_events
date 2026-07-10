<?php

namespace App\Console\Commands;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Scanning\Application\Jobs\RefreshEventCheckInSummaryJob;
use Illuminate\Console\Command;

final class RefreshCheckInSummary extends Command
{
    protected $signature = 'zonetec:checkin:refresh-summary {--event=}';

    protected $description = 'Recompute check-in summary counters for one event';

    public function handle(): int
    {
        $eventId = $this->option('event');
        if (! is_string($eventId) || $eventId === '') {
            $this->error('The --event= option is required.');

            return self::FAILURE;
        }

        $event = Event::query()->find($eventId);
        if ($event === null) {
            $this->error('Event not found.');

            return self::FAILURE;
        }

        (new RefreshEventCheckInSummaryJob($event->tenant_id, $event->id))->handle();
        $this->info("Refreshed check-in summary for event {$event->id}.");

        return self::SUCCESS;
    }
}
