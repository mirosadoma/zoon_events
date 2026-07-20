<?php

namespace App\Console\Commands;

use App\Modules\Attendees\Application\Actions\SyncAttendeeInviteStatus;
use Illuminate\Console\Command;

final class MarkNotAttendedInviteStatuses extends Command
{
    protected $signature = 'zonetec:attendees:mark-not-attended';

    protected $description = 'Mark registered invitees/attendees as not_attended after the event end date';

    public function handle(SyncAttendeeInviteStatus $sync): int
    {
        $updated = $sync->markNotAttendedForEndedEvents();
        $this->info("Updated {$updated} invite/attendee status row(s) to not_attended.");

        return self::SUCCESS;
    }
}
