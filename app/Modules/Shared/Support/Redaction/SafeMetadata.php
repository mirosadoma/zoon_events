<?php

namespace App\Modules\Shared\Support\Redaction;

final class SafeMetadata
{
    private const TRUNCATED = '[TRUNCATED]';

    public static function from(
        array $payload,
        int $maxStringLength = 128,
        int $maxDepth = 4,
        int $maxItems = 50,
    ): array {
        return self::truncate(
            SecretRedactor::redact($payload),
            $maxStringLength,
            $maxDepth,
            $maxItems,
            0,
        );
    }

    private static function truncate(
        array $payload,
        int $maxStringLength,
        int $maxDepth,
        int $maxItems,
        int $depth,
    ): array {
        if ($depth >= $maxDepth) {
            return ['truncated' => self::TRUNCATED];
        }

        $safe = [];
        $items = 0;

        foreach ($payload as $key => $value) {
            $items++;

            if ($items > $maxItems) {
                $safe['truncated'] = self::TRUNCATED;
                break;
            }

            if (is_array($value)) {
                $safe[$key] = self::truncate($value, $maxStringLength, $maxDepth, $maxItems, $depth + 1);

                continue;
            }

            if (is_string($value) && strlen($value) > $maxStringLength) {
                $safe[$key] = self::TRUNCATED;

                continue;
            }

            $safe[$key] = $value;
        }

        return $safe;
    }
}
