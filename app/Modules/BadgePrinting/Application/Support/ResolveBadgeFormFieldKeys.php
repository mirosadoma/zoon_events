<?php

namespace App\Modules\BadgePrinting\Application\Support;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;

final class ResolveBadgeFormFieldKeys
{
    /**
     * Public registration form fields that can appear on badges
     * (includes system name/email/phone; excludes display-only blocks).
     *
     * @return list<array{key: string, label_en: string, label_ar: string, type: string}>
     */
    public function forEvent(Event $event): array
    {
        $version = $this->resolveVersion($event);
        if ($version === null) {
            return [];
        }

        $fields = is_array($version->fields) ? $version->fields : [];
        $skipTypes = [
            'hidden', 'heading', 'divider', 'paragraph', 'consent',
            'event_logo', 'event_name', 'event_venue', 'event_dates', 'event_description',
            'event_categories', 'event_venue_select',
        ];
        $result = [];
        $seen = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = is_string($field['key'] ?? null) ? trim($field['key']) : '';
            $type = is_string($field['type'] ?? null) ? $field['type'] : '';

            if ($key === '' || isset($seen[$key]) || in_array($type, $skipTypes, true)) {
                continue;
            }

            $seen[$key] = true;
            $result[] = [
                'key' => $key,
                'label_en' => is_string($field['label_en'] ?? null) ? $field['label_en'] : $key,
                'label_ar' => is_string($field['label_ar'] ?? null) ? $field['label_ar'] : $key,
                'type' => $type !== '' ? $type : 'text',
            ];
        }

        return $result;
    }

    /** @return list<string> */
    public function keysForEvent(Event $event): array
    {
        return array_values(array_map(
            static fn (array $row): string => $row['key'],
            $this->forEvent($event),
        ));
    }

    private function resolveVersion(Event $event): ?RegistrationFormVersion
    {
        $query = RegistrationFormVersion::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id);

        if ($event->active_form_version_id !== null) {
            $active = (clone $query)->whereKey($event->active_form_version_id)->first();
            if ($active !== null) {
                return $active;
            }
        }

        return (clone $query)
            ->where('status', 'published')
            ->latest('version')
            ->first()
            ?? $query->latest('version')->first();
    }
}
