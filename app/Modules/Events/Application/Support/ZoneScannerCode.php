<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\Events\Infrastructure\Persistence\Models\EventZone;
use Illuminate\Validation\ValidationException;

final class ZoneScannerCode
{
    public static function generate(): string
    {
        return str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
    }

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return $trimmed;
    }

    public static function assertValid(string $code): void
    {
        if (! preg_match('/^\d{8}$/', $code)) {
            throw ValidationException::withMessages([
                'scanner_code' => 'Scanner code must be exactly 8 digits.',
            ]);
        }
    }

    public static function assertUnique(
        string $tenantId,
        string $eventId,
        string $code,
        ?int $exceptZoneId = null,
    ): void {
        self::assertValid($code);

        // Globally unique so scanner-app login can resolve event from code alone.
        $query = EventZone::query()->where('scanner_code', $code);

        if ($exceptZoneId !== null) {
            $query->where('id', '!=', $exceptZoneId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'scanner_code' => 'This scanner code is already used by another zone.',
            ]);
        }
    }

    public static function uniqueForEvent(string $tenantId, string $eventId, ?int $exceptZoneId = null): string
    {
        for ($i = 0; $i < 40; $i++) {
            $code = self::generate();
            $query = EventZone::query()->where('scanner_code', $code);

            if ($exceptZoneId !== null) {
                $query->where('id', '!=', $exceptZoneId);
            }

            if (! $query->exists()) {
                return $code;
            }
        }

        throw ValidationException::withMessages([
            'scanner_code' => 'Unable to generate a unique scanner code. Please try again.',
        ]);
    }
}
