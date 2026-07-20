<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('zonetec:about', function (): void {
    $this->components->info('Zonetec foundation bootstrap is installed.');
})->purpose('Display Zonetec foundation bootstrap information.');

Schedule::command('zonetec:audit:cleanup-exports')->hourly()->withoutOverlapping();
Schedule::command('zonetec:audit:verify --recent')->daily()->withoutOverlapping();
Schedule::command('zonetec:ticketing:expire-holds')->everyMinute()->withoutOverlapping();
Schedule::command('zonetec:payments:reconcile')->everyMinute()->withoutOverlapping();
Schedule::command('zonetec:notifications:deliver-due')->everyMinute()->withoutOverlapping();
Schedule::command('zonetec:identity:purge-expired')->daily()->withoutOverlapping();
Schedule::command('zonetec:identity:expire-stale')->daily()->withoutOverlapping();
Schedule::command('zonetec:attendees:mark-not-attended')->hourly()->withoutOverlapping();
Schedule::command('marketplace:activate-rentals')->everyMinute()->withoutOverlapping();
Schedule::command('marketplace:expire-rentals')->everyMinute()->withoutOverlapping();
Schedule::command('marketplace:finalize-statements')->everyFiveMinutes()->withoutOverlapping();
