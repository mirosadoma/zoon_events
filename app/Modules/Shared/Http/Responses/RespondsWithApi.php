<?php

namespace App\Modules\Shared\Http\Responses;

use App\Modules\Shared\Domain\Context\RequestContextStore;
use Illuminate\Http\JsonResponse;

trait RespondsWithApi
{
    protected function success(mixed $data, int $status = 200, array $meta = [], array $links = []): JsonResponse
    {
        $payload = [
            'data' => $data,
            'meta' => array_merge(
                ['correlation_id' => app(RequestContextStore::class)->current()?->correlationId->value],
                $meta,
            ),
        ];

        if ($links !== []) {
            $payload['links'] = $links;
        }

        return response()->json($payload, $status);
    }

    protected function empty(): JsonResponse
    {
        return response()->json(null, 204);
    }
}
