<?php

namespace App\Modules\BadgePrinting\Application\Support;

use App\Modules\Shared\Http\Problems\Phase3Problem;

final readonly class BadgeLayoutValidator
{
    private const ALLOWED_FIELDS = [
        'attendee_name',
        'company',
        'job_title',
        'qr',
        'ticket_type',
        'tier',
        'zone',
        'sponsor_logo_ref',
        'organizer_logo_ref',
        'color_code',
    ];

    public function validate(array $layout): void
    {
        foreach (array_keys($layout) as $key) {
            if (! in_array($key, self::ALLOWED_FIELDS, true)) {
                throw Phase3Problem::make('badge_template_invalid_field');
            }
        }
    }
}
