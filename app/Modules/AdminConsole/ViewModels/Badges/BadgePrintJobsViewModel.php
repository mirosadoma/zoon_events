<?php

namespace App\Modules\AdminConsole\ViewModels\Badges;

use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgePrintJob;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Illuminate\Support\Collection;

final readonly class BadgePrintJobsViewModel
{
    /**
     * @param  Collection<int, BadgePrintJob>  $jobs
     * @return array{event: array<string, mixed>, tenantId: string, printJobs: list<array<string, mixed>>}
     */
    public function index(Event $event, string $tenantId, Collection $jobs): array
    {
        return [
            'event' => $this->eventRow($event),
            'tenantId' => $tenantId,
            'printJobs' => $jobs->map(fn (BadgePrintJob $job): array => [
                'id' => $job->id,
                'attendee_id' => $job->attendee_id,
                'status' => $job->status,
                'failure_reason' => $job->failure_reason,
                'is_reprint' => $job->is_reprint,
                'reprint_reason' => $job->reprint_reason,
                'original_print_job_id' => $job->original_print_job_id,
                'printed_at' => $job->printed_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function eventRow(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
        ];
    }
}
