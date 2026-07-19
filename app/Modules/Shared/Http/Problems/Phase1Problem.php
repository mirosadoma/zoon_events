<?php

namespace App\Modules\Shared\Http\Problems;

use App\Exceptions\FoundationException;

final class Phase1Problem
{
    /** @var array<string,int> */
    public const STATUS = [
        'event_not_publishable' => 409,
        'event_not_unpublishable' => 409,
        'registration_closed' => 409,
        'ticket_unavailable' => 409,
        'inventory_conflict' => 409,
        'price_changed' => 409,
        'payment_action_required' => 409,
        'payment_pending' => 409,
        'payment_mismatch' => 409,
        'refund_not_allowed' => 409,
        'order_cancellation_not_allowed' => 409,
        'credential_invalid' => 422,
        'credential_expired' => 422,
        'credential_revoked' => 422,
        'credential_superseded' => 422,
        'notification_unavailable' => 503,
    ];

    public static function make(string $code, array $meta = []): FoundationException
    {
        $status = self::STATUS[$code] ?? 422;
        $title = $status === 503 ? 'Service unavailable' : ($status === 409 ? 'Conflict' : 'Validation failed');

        return new FoundationException($code, $status, $title, (string) __("phase1.{$code}"), $meta);
    }

    /** @param  list<string>  $missing */
    public static function eventNotPublishable(array $missing): FoundationException
    {
        return self::make('event_not_publishable', ['missing' => $missing]);
    }
}
