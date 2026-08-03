<?php

namespace App\Modules\Shared\Http\Problems;

use App\Exceptions\FoundationException;

final class Phase2Problem
{
    /** @var array<string,int> */
    public const STATUS = [
        'credential_not_active' => 409,
        'wallet_provider_unavailable' => 503,
        'wallet_pass_not_found' => 404,
        'scan_context_invalid' => 422,
        'override_reason_required' => 422,
        'override_not_permitted' => 403,
        'online_endpoint_does_not_accept_offline_mode' => 422,
        'offline_batch_conflict' => 409,
        'scanner_app_session_invalid' => 401,
        'scanner_app_zone_not_found' => 404,
    ];

    public static function make(string $code): FoundationException
    {
        $status = self::STATUS[$code] ?? 422;
        $title = match (true) {
            $status === 503 => 'Service unavailable',
            $status === 404 => 'Not found',
            $status === 409 => 'Conflict',
            $status === 403 => 'Forbidden',
            $status === 401 => 'Unauthorized',
            default => 'Validation failed',
        };

        return new FoundationException($code, $status, $title, (string) __("phase2.{$code}"));
    }
}
