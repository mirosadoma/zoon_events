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
        'attendee_type',
        'tier',
        'zone',
        'sponsor_logo_ref',
        'organizer_logo_ref',
        'color_code',
        'custom_text',
    ];

    public function validate(array $layout): void
    {
        foreach ($this->fieldKeys($layout) as $key) {
            if (! in_array($key, self::ALLOWED_FIELDS, true)) {
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
