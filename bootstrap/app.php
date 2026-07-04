<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\AdminConsole\Http\Middleware\AuthorizeDashboardPage;
use App\Modules\Authorization\Http\Middleware\RequirePermission;
use App\Modules\Events\Http\Middleware\ClearPublicEventContext;
use App\Modules\Events\Http\Middleware\ResolvePublicEventContext;
use App\Modules\Operations\Application\Telemetry\RecordRequestTelemetry;
use App\Modules\Shared\Http\Middleware\AssignRequestContext;
use App\Modules\Shared\Http\Middleware\RequireIdempotencyKey;
use App\Modules\Shared\Http\Middleware\ResolveLocale;
use App\Modules\Shared\Http\Middleware\SecurityHeaders;
use App\Modules\Shared\Http\Problems\FoundationProblemRenderer;
use App\Modules\Tenancy\Http\Middleware\ClearTenantContext;
use App\Modules\Tenancy\Http\Middleware\ResolveTenantContext;
use App\Providers\ModuleServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        ModuleServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: null,
        then: function (): void {
            require __DIR__.'/../routes/health.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'request.context' => AssignRequestContext::class,
            'locale' => ResolveLocale::class,
            'tenant.context' => ResolveTenantContext::class,
            'tenant.context.clear' => ClearTenantContext::class,
            'permission' => RequirePermission::class,
            'idempotency' => RequireIdempotencyKey::class,
            'dashboard.permission' => AuthorizeDashboardPage::class,
            'public.event.context' => ResolvePublicEventContext::class,
            'public.event.context.clear' => ClearPublicEventContext::class,
        ]);

        $middleware->append([
            AssignRequestContext::class,
            ResolveLocale::class,
            SecurityHeaders::class,
            RecordRequestTelemetry::class,
        ]);
        $middleware->web(append: [HandleInertiaRequests::class]);

        $middleware->priority([
            AssignRequestContext::class,
            ResolveLocale::class,
            ClearPublicEventContext::class,
            ResolvePublicEventContext::class,
            ClearTenantContext::class,
            ResolveTenantContext::class,
            SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $throwable, Request $request) {
            if (! $request->expectsJson() && ! str_starts_with($request->path(), 'api/')) {
                return null;
            }

            return app(FoundationProblemRenderer::class)->render($throwable, $request);
        });
    })
    ->create();
