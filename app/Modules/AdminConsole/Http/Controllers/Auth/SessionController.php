<?php

namespace App\Modules\AdminConsole\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Http\Requests\LoginRequest;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Identity\Application\AuthenticateUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class SessionController extends Controller
{
    public function __construct(
        private readonly AuthenticateUser $authenticate,
        private readonly AuditWriter $audit,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $this->authenticate->attempt($request->string('email')->toString(), $request->string('password')->toString());
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $this->audit->writePlatform('auth.session.started', 'succeeded', $user, targetType: 'user', targetId: $user->id);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            $this->audit->writePlatform('auth.session.ended', 'succeeded', $user, targetType: 'user', targetId: $user->id);
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
