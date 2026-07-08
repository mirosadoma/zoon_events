<?php

namespace App\Modules\Shared\Http\Problems;

use App\Exceptions\FoundationException;

final class Phase5Problem
{
    /** @var array<string,int> */
    public const STATUS = [
        'identity_consent_missing' => 409,
        'identity_not_verified' => 409,
        'identity_expired' => 409,
        'identity_rejected' => 409,
        'identity_provider_unavailable' => 503,
        'identity_callback_invalid' => 401,
    ];

    public static function make(string $code): FoundationException
    {
        $status = self::STATUS[$code] ?? 422;
        $title = match (true) {
            $status === 503 => 'Service unavailable',
            $status === 409 => 'Conflict',
            $status === 401 => 'Unauthorized',
            default => 'Validation failed',
        };

        return new FoundationException($code, $status, $title, (string) __("phase5.{$code}"));
    }
}
