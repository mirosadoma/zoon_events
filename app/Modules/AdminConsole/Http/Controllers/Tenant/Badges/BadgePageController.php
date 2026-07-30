<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\Badges;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\Application\Support\InertiaListPaginator;
use App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn\Concerns\AuthorizesTenantEventPage;
use App\Modules\AdminConsole\Http\Controllers\Tenant\Events\Concerns\ResolvesTenantEventFromRoute;
use App\Modules\AdminConsole\ViewModels\Badges\BadgePrintJobsViewModel;
use App\Modules\AdminConsole\ViewModels\Badges\BadgeTemplatePageViewModel;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgePrintJob;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BadgePageController extends Controller
{
    use AuthorizesTenantEventPage;
    use ResolvesTenantEventFromRoute;

    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly BadgeTemplatePageViewModel $templates,
        private readonly BadgePrintJobsViewModel $printJobs,
    ) {}

    public function templates(string $eventId): Response
    {
        [$context, $event] = $this->authorizeTenantEvent(
            $this->sessions,
            $this->permissions,
            $eventId,
            'badge.template.manage',
        );

        $rows = BadgeTemplate::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render(
            'tenant/badge-templates/Designer',
            $this->templates->index($event, $context->tenant->id, $rows),
        );
    }

    public function printJobs(Request $request, string $eventId): Response
    {
        $user = request()->user();
        abort_unless($user !== null, 403);

        $context = $this->sessions->tenantContextFor($user);
        abort_unless($context !== null, 403);
        abort_unless(
            $this->permissions->hasTenantPermission($context, 'badge.print')
            || $this->permissions->hasTenantPermission($context, 'badge.reprint'),
            403,
        );

        $event = $this->event($context, $eventId);

        $status = trim((string) $request->query('status', ''));
        if (! in_array($status, ['queued', 'printed', 'failed'], true)) {
            $status = '';
        }

        $perPage = InertiaListPaginator::PER_PAGE;
        $page = max(1, (int) $request->integer('page', 1));

        $groupedQuery = BadgePrintJob::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->selectRaw('attendee_id')
            ->selectRaw('MAX(id) as latest_id')
            ->selectRaw('MAX(printed_at) as last_printed_at')
            ->selectRaw(
                "SUM(CASE WHEN status = 'printed' THEN GREATEST(COALESCE(print_count, 1), 1) ELSE 0 END) as total_prints"
            )
            ->groupBy('attendee_id')
            ->orderByDesc('last_printed_at')
            ->orderByDesc('latest_id');

        $total = (int) BadgePrintJob::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->distinct()
            ->count('attendee_id');

        $groups = $groupedQuery->forPage($page, $perPage)->get();

        $jobsById = BadgePrintJob::query()
            ->whereIn('id', $groups->pluck('latest_id')->filter()->all())
            ->get()
            ->keyBy(fn (BadgePrintJob $job): string => (string) $job->id);

        $items = $groups->map(function (object $group) use ($jobsById): ?BadgePrintJob {
            $job = $jobsById->get((string) $group->latest_id);
            if ($job === null) {
                return null;
            }

            $totalPrints = max(0, (int) ($group->total_prints ?? 0));
            $job->setAttribute('print_count', max($totalPrints, (int) ($job->print_count ?? 0), $job->status === 'printed' ? 1 : 0));
            $job->setAttribute('is_reprint', $job->print_count > 1);

            return $job;
        })->filter()->values();

        return Inertia::render(
            'tenant/badges/PrintJobs',
            $this->printJobs->index(
                $event,
                $context->tenant->id,
                $items,
                ['status' => $status],
                [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => max(1, (int) ceil($total / $perPage)),
                ],
            ),
        );
    }
}
