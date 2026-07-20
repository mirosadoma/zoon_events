<?php

namespace App\Modules\AdminConsole\Http\Middleware;

use App\Modules\AdminConsole\Application\SiteSettingsRepository;
use App\Modules\Authorization\Application\PermissionEvaluator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSiteIsAvailable
{
    public function __construct(
        private readonly SiteSettingsRepository $settings,
        private readonly PermissionEvaluator $permissions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $config = $this->settings->current();

        if (! $config->maintenance_enabled) {
            return $next($request);
        }

        if ($this->canBypassMaintenance($request)) {
            return $next($request);
        }

        if ($request->routeIs('maintenance', 'login', 'logout', 'locale.update')) {
            return $next($request);
        }

        if ($request->is('api/*')) {
            return response()->json([
                'title' => 'Maintenance',
                'detail' => app()->getLocale() === 'ar'
                    ? ($config->maintenance_message_ar ?: 'المنصة قيد الصيانة.')
                    : ($config->maintenance_message_en ?: 'The platform is under maintenance.'),
            ], 503);
        }

        return redirect()->route('maintenance');
    }

    private function canBypassMaintenance(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        return $this->permissions->hasPlatformPermission($user, 'platform.tenant.manage');
    }
}
