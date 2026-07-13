<?php

namespace App\Modules\Registration\Application\Support;

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
            return '+'.substr($digits, 2);
        }

        if (str_starts_with($trimmed, '+')) {
            return '+'.$digits;
        }

        return $digits;
    }

    public static function isValid(string $value): bool
    {
        $normalized = self::normalize($value);

        return $normalized !== '' && preg_match('/^\+?[0-9]{8,15}$/', $normalized) === 1;
    }
}
