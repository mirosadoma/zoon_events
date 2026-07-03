<?php

namespace App\Http\Middleware;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();
        $permissions = $user instanceof User
            ? collect(PermissionSeeder::definitions())
                ->where('scope', 'platform')
                ->pluck('key')
                ->filter(fn (string $permission): bool => Gate::forUser($user)->allows($permission))
                ->values()
                ->all()
            : [];

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user instanceof User ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'preferred_locale' => $user->preferred_locale,
                ] : null,
            ],
            'locale' => app()->getLocale(),
            'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
            'permissions' => $permissions,
            'flash' => ['status' => fn () => $request->session()->get('status')],
        ];
    }
}
