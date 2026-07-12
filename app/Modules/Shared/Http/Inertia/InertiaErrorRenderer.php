<?php

namespace App\Modules\Shared\Http\Inertia;

use App\Modules\Shared\Support\LocaleDetector;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class InertiaErrorRenderer
{
    /**
     * @var list<int>
     */
    private const CUSTOM_STATUSES = [401, 403, 404, 405, 419, 429, 500, 503];

    public function shouldRender(Request $request): bool
    {
        if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
            return false;
        }

        if (in_array($request->path(), ['up', 'health'], true)) {
            return false;
        }

        return true;
    }

    public function resolveStatus(Throwable $throwable): ?int
    {
        if ($throwable instanceof ModelNotFoundException) {
            return 404;
        }

        if ($throwable instanceof AuthenticationException) {
            return 401;
        }

        if ($throwable instanceof AuthorizationException) {
            return 403;
        }

        if ($throwable instanceof TokenMismatchException) {
            return 419;
        }

        if ($throwable instanceof ThrottleRequestsException) {
            return 429;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            $status = $throwable->getStatusCode();

            return in_array($status, self::CUSTOM_STATUSES, true) ? $status : null;
        }

        return 500;
    }

    public function render(Throwable $throwable, Request $request): ?Response
    {
        if (! $this->shouldRender($request)) {
            return null;
        }

        if ($throwable instanceof AuthenticationException) {
            return null;
        }

        $status = $this->resolveStatus($throwable);

        if ($status === null) {
            return null;
        }

        if (
            config('app.debug')
            && $status === 500
            && ! $throwable instanceof HttpExceptionInterface
        ) {
            return null;
        }

        $locale = LocaleDetector::detect($request);
        app()->setLocale($locale);

        return Inertia::render('errors/HttpError', [
            'statusCode' => $status,
        ])->toResponse($request)->setStatusCode($status);
    }
}
