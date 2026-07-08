<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\Badges;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn\Concerns\AuthorizesTenantEventPage;
use App\Modules\AdminConsole\ViewModels\Badges\BadgePrintJobsViewModel;
use App\Modules\AdminConsole\ViewModels\Badges\BadgeTemplatePageViewModel;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgePrintJob;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BadgePageController extends Controller
{
    use AuthorizesTenantEventPage;

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

        $event = Event::query()
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($eventId);

        $query = BadgePrintJob::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->latest('created_at')
            ->limit(200);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return Inertia::render(
            'tenant/badges/PrintJobs',
            $this->printJobs->index($event, $context->tenant->id, $query->get()),
        );
    }
}
