<?php

namespace App\Modules\Registration\Application\Support;

/**
 * Public registration phones must be a local Saudi mobile:
 * exactly 10 digits starting with 05 (e.g. 0512312312).
 */
final class RegistrationPhoneNormalizer
{
    public static function normalize(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '9665') && strlen($digits) === 12) {
            return '0'.substr($digits, 3);
        }

        return $digits;
    }

    public static function isValid(string $value): bool
    {
        $normalized = self::normalize($value);

        return preg_match('/^05[0-9]{8}$/', $normalized) === 1;
    }
}
