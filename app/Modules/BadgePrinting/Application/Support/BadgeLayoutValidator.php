<?php

namespace App\Modules\BadgePrinting\Application\Support;

use App\Modules\Shared\Http\Problems\Phase3Problem;

final readonly class BadgeLayoutValidator
{
    public const CORE_FIELDS = [
        'attendee_name',
        'email',
        'phone',
        'company',
        'job_title',
        'qr',
        'ticket_type',
        'attendee_type',
        'tier',
        'zone',
        'sponsor_logo_ref',
        'organizer_logo_ref',
        'color_code',
        'custom_text',
    ];

    /**
     * @param  list<string>  $extraAllowedFields  Registration form field keys allowed on the badge.
     */
    public function validate(array $layout, array $extraAllowedFields = []): void
    {
        $allowed = array_values(array_unique([...self::CORE_FIELDS, ...$extraAllowedFields]));

        foreach ($this->fieldKeys($layout) as $key) {
            if (! in_array($key, $allowed, true)) {
                throw Phase3Problem::make('badge_template_invalid_field');
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $layout
     * @return list<string>
     */
    private function fieldKeys(array $layout): array
    {
        if ($layout === []) {
            return [];
        }

        if (array_is_list($layout)) {
            $keys = [];
            foreach ($layout as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $field = $item['field'] ?? null;
                if (is_string($field) && $field !== '') {
                    $keys[] = $field;
                }
            }

            return array_values(array_unique($keys));
        }

        return array_map(strval(...), array_keys($layout));
    }
}
