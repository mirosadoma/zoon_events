<?php

namespace App\Modules\Shared\Http\Problems;

use App\Exceptions\FoundationException;

final class Phase3Problem
{
    /** @var array<string,int> */
    public const STATUS = [
        'kiosk_session_invalid'           => 401,
        'kiosk_session_unconfirmed'       => 401,
        'kiosk_retired'                   => 401,
        'lookup_too_many_matches'         => 422,
        'lookup_confirmation_required'    => 422,
        'lookup_confirmation_invalid'     => 422,
        'badge_template_not_active'       => 409,
        'badge_template_invalid_field'    => 422,
        'badge_reprint_reason_required'   => 422,
        'badge_reprint_not_permitted'     => 403,
        'badge_no_prior_print_job'        => 409,
        'badge_print_not_permitted'       => 403,
        'printer_unavailable'             => 503,
        'printer_error'                   => 409,
        'payload_rejected'                => 422,
        'checkin_desk_not_permitted'      => 403,
        'walk_up_registration_disabled'   => 403,
        'walk_up_payment_not_collectible' => 422,
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
            default         => 'Validation failed',
        };

        return new FoundationException($code, $status, $title, (string) __("phase3.{$code}"));
    }
}
