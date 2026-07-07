<?php

namespace App\Modules\Shared\Http\Problems;

use App\Exceptions\FoundationException;

final class Phase4Problem
{
    /** @var array<string,int> */
    public const STATUS = [
        'acs_integration_invalid' => 401,
        'acs_capability_denied' => 403,
        'acs_zone_unmapped' => 404,
        'acs_lane_unmapped' => 404,
        'acs_event_out_of_scope' => 404,
        'acs_duplicate_external_id' => 409,
        'acs_invalid_time_window' => 422,
        'acs_config_not_permitted' => 403,
        'acs_events_not_permitted' => 403,
        'acs_emergency_not_permitted' => 403,
    ];

    public static function make(string $code): FoundationException
    {
        $status = self::STATUS[$code] ?? 422;
        $title = match (true) {
            $status === 404 => 'Not found',
            $status === 409 => 'Conflict',
            $status === 403 => 'Forbidden',
            $status === 401 => 'Unauthorized',
            default => 'Validation failed',
        };

        return new FoundationException($code, $status, $title, (string) __("phase4.{$code}"));
    }
}
