<?php

namespace App\Modules\AdminConsole\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Models\PasswordResetOtp;
use App\Modules\Identity\Mail\PasswordResetOtpMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ForgotPasswordController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:254'],
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
        $user = User::query()->where('email', $email)->first();

        // Always redirect to OTP step to avoid email enumeration.
        $token = Str::random(48);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        if ($user !== null) {
            PasswordResetOtp::query()->where('email', $email)->delete();
            PasswordResetOtp::query()->create([
                'email' => $email,
                'token' => $token,
                'code_hash' => hash('sha256', $code),
                'expires_at' => now()->addMinutes(15),
                'attempts' => 0,
            ]);

            Mail::to($email)->send(new PasswordResetOtpMail($code, $locale));

            if (app()->environment('local')) {
                logger()->info('password_reset.otp_code', [
                    'email' => $email,
                    'code' => $code,
                    'token' => $token,
                ]);
            }
        } else {
            // Dummy token so the flow can still show the OTP page without leaking existence.
            $token = Str::random(48);
        }

        return redirect("/{$locale}/forgot-password/otp/{$token}");
    }

    public function showOtp(Request $request): Response
    {
        return Inertia::render('Auth/ForgotPasswordOtp', [
            'token' => (string) $request->route('token'),
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $token = (string) $request->route('token');
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
        $otp = PasswordResetOtp::query()->where('token', $token)->first();

        if ($otp === null || $otp->isExpired() || $otp->isVerified()) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired code.'],
            ]);
        }

        if ($otp->attempts >= 5) {
            throw ValidationException::withMessages([
                'code' => ['Too many attempts. Request a new code.'],
            ]);
        }

        $otp->increment('attempts');

        if (! hash_equals($otp->code_hash, hash('sha256', $validated['code']))) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired code.'],
            ]);
        }

        $resetToken = Str::random(48);
        $otp->forceFill([
            'verified_at' => now(),
            'reset_token' => $resetToken,
            'reset_token_expires_at' => now()->addMinutes(15),
        ])->save();

        return redirect("/{$locale}/forgot-password/reset/{$resetToken}");
    }

    public function showReset(Request $request): Response
    {
        $resetToken = (string) $request->route('resetToken');
        $otp = PasswordResetOtp::query()
            ->where('reset_token', $resetToken)
            ->first();

        if ($otp === null || ! $otp->resetTokenIsValid()) {
            abort(404);
        }

        return Inertia::render('Auth/ResetPassword', [
            'resetToken' => $resetToken,
            'email' => $otp->email,
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $resetToken = (string) $request->route('resetToken');
        $otp = PasswordResetOtp::query()
            ->where('reset_token', $resetToken)
            ->first();

        if ($otp === null || ! $otp->resetTokenIsValid()) {
            throw ValidationException::withMessages([
                'password' => ['This reset link is invalid or expired.'],
            ]);
        }

        $user = User::query()->where('email', $otp->email)->firstOrFail();
        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        PasswordResetOtp::query()->where('email', $otp->email)->delete();

        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

        return redirect("/{$locale}/login")->with('status', 'password-reset');
    }
}
