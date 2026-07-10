<?php

namespace App\Modules\AdminConsole\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\OrganizerRegistrationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class OrganizerRegistrationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/RegisterOrganizer');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'min:8', 'max:1024', 'confirmed'],
            'organization_name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $email = mb_strtolower($validated['email']);

        if (OrganizerRegistrationRequest::query()->where('email', $email)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages([
                'email' => 'A pending registration request already exists for this email.',
            ]);
        }

        OrganizerRegistrationRequest::query()->create([
            'name' => $validated['name'],
            'email' => $email,
            'password_hash' => Hash::make($validated['password']),
            'organization_name' => $validated['organization_name'],
            'phone' => $validated['phone'] ?? null,
            'message' => $validated['message'] ?? null,
            'status' => 'pending',
        ]);

        return redirect()->route('register.organizer')->with('status', 'registration-submitted');
    }
}
