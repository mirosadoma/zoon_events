<?php

namespace App\Http\Middleware;

use App\Modules\AdminConsole\Application\SiteSettingsRepository;
use App\Modules\AdminConsole\Application\SessionContextBuilder;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $context = app(SessionContextBuilder::class)->build($request);
        $siteSettings = app(SiteSettingsRepository::class)->toPublicArray();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $context['session']['user'] ?? null,
            ],
            'session' => $context['session'],
            'can' => $context['can'],
            'locale' => app()->getLocale(),
            'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
            'permissions' => $context['permissions'],
            'siteSettings' => $siteSettings,
            'flash' => ['status' => fn () => $request->session()->get('status')],
        ];
    }
}
