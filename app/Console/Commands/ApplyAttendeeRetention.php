<?php

namespace App\Console\Commands;

use App\Modules\Attendees\Application\Jobs\AnonymizeEligibleAttendees;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class ApplyAttendeeRetention extends Command
{
    protected $signature = 'zonetec:attendees:retention
        {tenant : Tenant ULID}
        {--before= : Approved exclusive retention cutoff in ISO-8601}
        {--execute : Apply changes; omitted means dry-run}';

    protected $description = 'Preview or apply tenant-scoped attendee anonymization while honoring legal holds';

    public function handle(AnonymizeEligibleAttendees $job): int
    {
        try {
            $before = Carbon::parse((string) $this->option('before'));
        } catch (\Throwable) {
            throw new InvalidArgumentException('A valid approved --before cutoff is required.');
        }
        $execute = (bool) $this->option('execute');
        if ($execute && ! $this->confirm('Apply irreversible anonymization for this tenant?', false)) {
            return self::FAILURE;
        }

        $result = $job->handle((string) $this->argument('tenant'), $before, ! $execute);
        $this->table(['Mode', 'Eligible', 'Anonymized'], [[
            $execute ? 'execute' : 'dry-run',
            $result['eligible'],
            $result['anonymized'],
        ]]);

        return self::SUCCESS;
    }
}
