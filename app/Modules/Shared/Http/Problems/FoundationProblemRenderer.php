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
        $safeThrowable = $throwable instanceof InvalidArgumentException
            ? FoundationException::validation('validation_failed', 'One or more fields are invalid.')
            : $throwable;

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
}
