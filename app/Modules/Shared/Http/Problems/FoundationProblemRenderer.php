<?php

namespace App\Modules\Shared\Http\Problems;

use App\Exceptions\FoundationException;
use App\Modules\Shared\Domain\Context\CorrelationId;
use App\Modules\Shared\Domain\Context\RequestContextStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

final readonly class FoundationProblemRenderer
{
    public function __construct(private RequestContextStore $contexts) {}

    public function render(Throwable $throwable, Request $request): JsonResponse
    {
        $correlationId = $this->contexts->current()?->correlationId->value
            ?? CorrelationId::fromHeader($request->headers->get('X-Correlation-ID'))->value;
        if ($throwable instanceof InvalidArgumentException) {
            Log::error('Request failed because application configuration or state is invalid.', [
                'correlation_id' => $correlationId,
                'exception' => $throwable,
                'path' => $request->path(),
            ]);
        }

        $safeThrowable = match (true) {
            $throwable instanceof InvalidArgumentException
                && $this->isServiceConfigurationFailure($throwable) => FoundationException::serviceUnavailable(
                    'This operation is temporarily unavailable. Please try again later.',
                ),
            $throwable instanceof InvalidArgumentException => FoundationException::validation(),
            default => $throwable,
        };

        if (! $safeThrowable instanceof FoundationException) {
            Log::error($safeThrowable->getMessage(), [
                'exception' => $safeThrowable,
                'path' => $request->path(),
            ]);
        }
        $problem = ProblemFactory::fromThrowable(
            $safeThrowable,
            '/'.$request->path(),
            $correlationId,
        );

        return response()->json(
            $problem->toArray(),
            $problem->status,
            [
                'Content-Type' => 'application/problem+json',
                'X-Correlation-ID' => $problem->correlationId,
            ],
        );
    }

    private function isServiceConfigurationFailure(InvalidArgumentException $throwable): bool
    {
        $message = $throwable->getMessage();

        return str_contains($message, 'key is unavailable')
            || str_contains($message, 'key is not active')
            || str_contains($message, 'key is invalid')
            || str_contains($message, 'secret reference is unavailable')
            || str_contains($message, 'adapter is unavailable')
            || str_contains($message, 'gateway is not configured')
            || str_contains($message, 'price tiers are ambiguous');
    }
}
