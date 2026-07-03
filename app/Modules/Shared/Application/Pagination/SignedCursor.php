<?php

namespace App\Modules\Shared\Application\Pagination;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use InvalidArgumentException;

final class SignedCursor
{
    public static function issue(
        string $createdAt,
        string $id,
        string $scope,
        array $filters,
        int $expiresInMinutes = 60,
    ): string {
        $payload = [
            'created_at' => $createdAt,
            'id' => $id,
            'scope' => $scope,
            'filters' => Arr::sortRecursive($filters),
            'expires_at' => CarbonImmutable::now('UTC')->addMinutes($expiresInMinutes)->toIso8601String(),
        ];

        $encodedPayload = base64_encode((string) json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $encodedPayload, self::signingKey());

        return $encodedPayload.'.'.$signature;
    }

    public static function decode(string $cursor, string $scope, array $filters): CursorPageCursor
    {
        [$payload, $signature] = explode('.', $cursor, 2) + [null, null];

        if (! $payload || ! $signature) {
            throw new InvalidArgumentException('The cursor is malformed.');
        }

        $expectedSignature = hash_hmac('sha256', $payload, self::signingKey());

        if (! hash_equals($expectedSignature, $signature)) {
            throw new InvalidArgumentException('The cursor signature is invalid.');
        }

        /** @var array{created_at:string,id:string,scope:string,filters:array,expires_at:string} $decoded */
        $decoded = json_decode(base64_decode($payload, true) ?: '', true, 512, JSON_THROW_ON_ERROR);

        if ($decoded['scope'] !== $scope || Arr::sortRecursive($decoded['filters']) !== Arr::sortRecursive($filters)) {
            throw new InvalidArgumentException('The cursor scope does not match the current request.');
        }

        if (CarbonImmutable::parse($decoded['expires_at'])->isPast()) {
            throw new InvalidArgumentException('The cursor has expired.');
        }

        return new CursorPageCursor(
            createdAt: $decoded['created_at'],
            id: $decoded['id'],
        );
    }

    private static function signingKey(): string
    {
        return (string) config('app.key');
    }
}
