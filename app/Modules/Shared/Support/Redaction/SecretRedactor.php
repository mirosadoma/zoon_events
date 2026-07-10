<?php

namespace App\Modules\Shared\Support\Redaction;

final class SecretRedactor
{
    public const REDACTED = '[REDACTED]';

    private const SENSITIVE_KEYS = [
        'password',
        'token',
        'secret',
        'key',
        'api_key',
        'connection_string',
        'authorization',
        'cookie',
    ];

    private const VALUE_PATTERNS = [
        '/bearer\s+[a-z0-9\-._~+\/]+=*/i',
        '/(?:sk|pk)_(?:live|test)_[a-z0-9]+/i',
        '/mysql:\/\/[^@\s]+@/i',
        '/postgres(?:ql)?:\/\/[^@\s]+@/i',
    ];

    public static function redact(array $payload): array
    {
        $redacted = [];

        foreach ($payload as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (self::isSensitiveKey($normalizedKey)) {
                $redacted[$key] = self::REDACTED;

                continue;
            }

            $redacted[$key] = is_array($value)
                ? self::redact($value)
                : self::redactScalar($value);
        }

        return $redacted;
    }

    private static function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if (str_contains($key, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }

    private static function redactScalar(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        foreach (self::VALUE_PATTERNS as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return self::REDACTED;
            }
        }

        return $value;
    }
}
