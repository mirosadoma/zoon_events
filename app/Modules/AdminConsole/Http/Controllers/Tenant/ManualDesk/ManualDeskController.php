<?php

namespace App\Modules\AdminConsole\Http\Controllers\Tenant\ManualDesk;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\Http\Controllers\Tenant\CheckIn\Concerns\AuthorizesTenantEventPage;
use App\Modules\AdminConsole\ViewModels\ManualDesk\ManualDeskViewModel;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType;
use Inertia\Inertia;
use Inertia\Response;

final class ManualDeskController extends Controller
{
    use AuthorizesTenantEventPage;

    public function __construct(
        private readonly SessionContextBuilder $sessions,
        private readonly PermissionEvaluator $permissions,
        private readonly ManualDeskViewModel $viewModel,
    ) {}

    public function index(string $eventId): Response
    {
        [$context, $event] = $this->authorizeTenantEvent(
            $this->sessions,
            $this->permissions,
            $eventId,
            'checkin.desk.perform',
        );

        $ticketTypes = TicketType::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->orderBy('name_en')
            ->get();

        return Inertia::render(
            'tenant/manual-desk/Desk',
            $this->viewModel->make($event, $context->tenant->id, $ticketTypes),
        );
    }

    public function walkUp(string $eventId): Response
    {
        [$context, $event] = $this->authorizeTenantEvent(
            $this->sessions,
            $this->permissions,
            $eventId,
            'attendee.walkup.register',
        );

        $ticketTypes = TicketType::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->orderBy('name_en')
            ->get();

        return Inertia::render(
            'tenant/manual-desk/WalkUp',
            $this->viewModel->make($event, $context->tenant->id, $ticketTypes),
        );
    }
}
