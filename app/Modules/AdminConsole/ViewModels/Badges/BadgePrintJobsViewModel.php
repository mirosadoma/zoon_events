<?php

namespace App\Modules\AdminConsole\ViewModels\Badges;

use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgePrintJob;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Illuminate\Support\Collection;

final readonly class BadgePrintJobsViewModel
{
    /**
     * @param  Collection<int, BadgePrintJob>  $jobs
     * @param  array{status?: string}  $filters
     * @param  array{page: int, per_page: int, total: int, last_page: int}  $pagination
     * @return array{
     *     event: array<string, mixed>,
     *     tenantId: string,
     *     printJobs: list<array<string, mixed>>,
     *     filters: array{status: string},
     *     pagination: array{page: int, per_page: int, total: int, last_page: int}
     * }
     */
    public function index(
        Event $event,
        string $tenantId,
        Collection $jobs,
        array $filters = [],
        array $pagination = ['page' => 1, 'per_page' => 15, 'total' => 0, 'last_page' => 1],
    ): array {
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
            'filters' => [
                'status' => (string) ($filters['status'] ?? ''),
            ],
            'pagination' => [
                'page' => (int) $pagination['page'],
                'per_page' => (int) $pagination['per_page'],
                'total' => (int) $pagination['total'],
                'last_page' => (int) $pagination['last_page'],
            ],
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
