<?php

namespace App\Modules\Shared\Http\Problems;

use App\Exceptions\FoundationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ProblemFactory
{
    public static function fromThrowable(Throwable $throwable, string $instance, string $correlationId): ProblemDetails
    {
        return match (true) {
            $throwable instanceof FoundationException => new ProblemDetails(
                type: self::typeFor($throwable->problemCode),
                title: $throwable->title,
                status: $throwable->status,
                code: $throwable->problemCode,
                detail: $throwable->detail(),
                instance: $instance,
                correlationId: $correlationId,
            ),
            $throwable instanceof AuthenticationException => new ProblemDetails(
                type: self::typeFor('unauthenticated'),
                title: 'Unauthenticated',
                status: 401,
                code: 'unauthenticated',
                detail: 'Authentication is required to access this resource.',
                instance: $instance,
                correlationId: $correlationId,
            ),
            $throwable instanceof AuthorizationException => new ProblemDetails(
                type: self::typeFor('forbidden'),
                title: 'Forbidden',
                status: 403,
                code: 'forbidden',
                detail: 'You are not allowed to perform this action.',
                instance: $instance,
                correlationId: $correlationId,
            ),
            $throwable instanceof ValidationException => new ProblemDetails(
                type: self::typeFor('validation_failed'),
                title: 'Validation failed',
                status: 422,
                code: 'validation_failed',
                detail: 'One or more fields are invalid.',
                instance: $instance,
                correlationId: $correlationId,
                errors: $throwable->errors(),
            ),
            $throwable instanceof ThrottleRequestsException => new ProblemDetails(
                type: self::typeFor('rate_limited'),
                title: 'Rate limited',
                status: 429,
                code: 'rate_limited',
                detail: 'Too many requests were made for this operation.',
                instance: $instance,
                correlationId: $correlationId,
            ),
            $throwable instanceof HttpExceptionInterface && $throwable->getStatusCode() === 429 => new ProblemDetails(
                type: self::typeFor('rate_limited'),
                title: 'Rate limited',
                status: 429,
                code: 'rate_limited',
                detail: 'Too many requests were made for this operation.',
                instance: $instance,
                correlationId: $correlationId,
            ),
            $throwable instanceof HttpExceptionInterface && $throwable->getStatusCode() === 404 => new ProblemDetails(
                type: self::typeFor('resource_not_found'),
                title: 'Resource not found',
                status: 404,
                code: 'resource_not_found',
                detail: 'The requested resource could not be found.',
                instance: $instance,
                correlationId: $correlationId,
            ),
            $throwable instanceof HttpExceptionInterface && $throwable->getStatusCode() === 405 => new ProblemDetails(
                type: self::typeFor('method_not_allowed'),
                title: 'Method not allowed',
                status: 405,
                code: 'method_not_allowed',
                detail: 'The requested method is not allowed for this resource.',
                instance: $instance,
                correlationId: $correlationId,
            ),
            $throwable instanceof HttpExceptionInterface && $throwable->getStatusCode() === 403 => new ProblemDetails(
                type: self::typeFor('forbidden'),
                title: 'Forbidden',
                status: 403,
                code: 'forbidden',
                detail: 'You are not allowed to perform this action.',
                instance: $instance,
                correlationId: $correlationId,
            ),
            $throwable instanceof HttpExceptionInterface && $throwable->getStatusCode() === 401 => new ProblemDetails(
                type: self::typeFor('unauthenticated'),
                title: 'Unauthenticated',
                status: 401,
                code: 'unauthenticated',
                detail: 'Authentication is required to access this resource.',
                instance: $instance,
                correlationId: $correlationId,
            ),
            $throwable instanceof HttpExceptionInterface && $throwable->getStatusCode() === 503 => new ProblemDetails(
                type: self::typeFor('service_unavailable'),
                title: 'Service unavailable',
                status: 503,
                code: 'service_unavailable',
                detail: 'The service is temporarily unavailable.',
                instance: $instance,
                correlationId: $correlationId,
            ),
            default => new ProblemDetails(
                type: self::typeFor('service_unavailable'),
                title: 'Service unavailable',
                status: 503,
                code: 'service_unavailable',
                detail: 'The service is temporarily unavailable.',
                instance: $instance,
                correlationId: $correlationId,
            ),
        };
    }

    private static function typeFor(string $code): string
    {
        return "https://docs.zonetec.example/problems/{$code}";
    }
}
