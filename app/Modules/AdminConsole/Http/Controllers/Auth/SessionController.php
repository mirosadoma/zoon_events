<?php

namespace App\Modules\AdminConsole\Http\Controllers\Auth;

use App\Exceptions\FoundationException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AdminConsole\Http\Requests\LoginRequest;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Identity\Application\AuthenticateUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

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
        $email = $request->string('email')->toString();
        $password = $request->string('password')->toString();

        try {
            $user = $this->authenticate->attempt($email, $password);
        } catch (FoundationException $exception) {
            $this->recordFailedLogin($email, $exception);

            throw ValidationException::withMessages([
                'email' => [$exception->detail()],
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $user->forceFill(['last_authenticated_at' => now()])->save();
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

    private function recordFailedLogin(string $email, FoundationException $exception): void
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $known = User::query()->where('email', $normalizedEmail)->first();

        try {
            $this->audit->write(
                'platform',
                null,
                'auth.failed',
                'failed',
                $known,
                $exception->problemCode === 'user_inactive' ? 'user_inactive' : 'invalid_credentials',
                targetType: $known ? 'user' : null,
                targetId: $known?->id,
                metadata: ['identity_fingerprint' => hash('sha256', $normalizedEmail)],
            );
        } catch (Throwable) {
            // Failed login feedback must not be masked by audit write failures.
        }
    }
}
