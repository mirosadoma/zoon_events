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

        $query = BadgePrintJob::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->latest('created_at')
            ->orderByDesc('id');

        $result = InertiaListPaginator::paginate($query, $request);

        return Inertia::render(
            'tenant/badges/PrintJobs',
            $this->printJobs->index(
                $event,
                $context->tenant->id,
                $result['items'],
                ['status' => $status],
                $result['pagination'],
            ),
        );
    }
}
