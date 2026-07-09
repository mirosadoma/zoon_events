<?php

namespace App\Modules\AdminConsole\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\Actions\ApproveOrganizerRegistrationAction;
use App\Modules\AdminConsole\Application\Actions\RejectOrganizerRegistrationAction;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\OrganizerRegistrationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class OrganizerRequestAdminController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('platform.user.manage');

        $requests = OrganizerRegistrationRequest::query()
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (OrganizerRegistrationRequest $row): array => [
                'id' => (string) $row->id,
                'name' => $row->name,
                'email' => $row->email,
                'organization_name' => $row->organization_name,
                'phone' => $row->phone,
                'message' => $row->message,
                'status' => $row->status,
                'rejection_reason' => $row->rejection_reason,
                'reviewed_at' => $row->reviewed_at?->toIso8601String(),
                'created_at' => $row->created_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('platform/OrganizerRequests', [
            'requests' => $requests,
        ]);
    }

    public function approve(string $requestId, ApproveOrganizerRegistrationAction $action): RedirectResponse
    {
        Gate::authorize('platform.user.manage');

        $registration = OrganizerRegistrationRequest::query()->findOrFail($requestId);
        $action->execute($registration, request()->user());

        return back()->with('status', 'organizer-approved');
    }

    public function reject(string $requestId, Request $request, RejectOrganizerRegistrationAction $action): RedirectResponse
    {
        Gate::authorize('platform.user.manage');

        $registration = OrganizerRegistrationRequest::query()->findOrFail($requestId);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $action->execute($registration, $request->user(), $validated['reason']);

        return back()->with('status', 'organizer-rejected');
    }
}
