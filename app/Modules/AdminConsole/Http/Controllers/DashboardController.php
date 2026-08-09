<?php

namespace App\Modules\AdminConsole\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Application\DashboardOverviewBuilder;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use App\Modules\AdminConsole\ViewModels\Admin\ProfileViewModel;
use App\Modules\AdminConsole\ViewModels\FoundationDashboardViewModel;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardOverviewBuilder $overview,
        private readonly SessionContextBuilder $sessionContext,
        private readonly TenantContextStore $contexts,
    ) {}

    public function __invoke(): Response
    {
        $user = request()->user();
        $context = $this->contexts->currentOrNull()
            ?? ($user !== null ? $this->sessionContext->tenantContextFor($user) : null);

        return Inertia::render('FoundationDashboard', (new FoundationDashboardViewModel(
            $context !== null ? 'tenant' : 'platform',
            'Dashboard overview',
            ['events', 'orders', 'attendees', 'credentials', 'checkin', 'audit'],
            $this->overview->build($context),
        ))->toArray());
    }

    public function profile(): Response
    {
        $user = request()->user();
        abort_if($user === null, 403);
        $built = $this->sessionContext->build(request());
        $session = $built['session'];

        return Inertia::render('Profile', (new ProfileViewModel(
            $user,
            is_array($session) ? (string) ($session['role_label'] ?? 'Operator') : 'Operator',
            is_array($session) ? ($session['tenant'] ?? null) : null,
        ))->toArray());
    }

    public function updateProfile(): RedirectResponse
    {
        $user = request()->user();
        abort_if($user === null, 403);

        $validated = request()->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:30'],
            'preferred_locale' => ['required', 'in:en,ar'],
        ]);

        $user->forceFill($validated)->save();
        Cookie::queue('locale', $validated['preferred_locale'], 60 * 24 * 365);

        return back()->with('status', 'profile-updated');
    }

    public function updatePassword(): RedirectResponse
    {
        $user = request()->user();
        abort_if($user === null, 403);

        $validated = request()->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return back()->with('status', 'profile-password-updated');
    }
}
