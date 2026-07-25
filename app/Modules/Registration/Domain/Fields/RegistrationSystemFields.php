<?php

namespace App\Modules\Registration\Domain\Fields;

use InvalidArgumentException;

final class RegistrationSystemFields
{
    /** @var list<string> */
    public const KEYS = ['full_name', 'email', 'phone'];

    /** @return list<array<string, mixed>> */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'full_name',
                'type' => FormFieldType::Text->value,
                'label_en' => 'Full name',
                'label_ar' => 'الاسم الكامل',
                'required' => true,
                'visibility' => 'public',
                'system' => true,
            ],
            [
                'key' => 'email',
                'type' => FormFieldType::Email->value,
                'label_en' => 'Email',
                'label_ar' => 'البريد الإلكتروني',
                'required' => true,
                'visibility' => 'public',
                'system' => true,
            ],
            [
                'key' => 'phone',
                'type' => FormFieldType::Phone->value,
                'label_en' => 'Phone number',
                'label_ar' => 'رقم الجوال',
                'required' => true,
                'visibility' => 'public',
                'system' => true,
            ],
        ];
    }

    public static function isSystemKey(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }

    /**
     * Keep organizer field order while ensuring every system field is present exactly once.
     *
     * @param  list<array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    public static function enforce(array $fields): array
    {
        $definitions = [];
        foreach (self::definitions() as $definition) {
            $definitions[$definition['key']] = $definition;
        }

        $seen = [];
        $result = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }

            if (self::isSystemKey($key)) {
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $definition = $definitions[$key];
                $merged = $definition;
                if (isset($field['width']) && in_array($field['width'], ['full', 'half', 'third'], true)) {
                    $merged['width'] = $field['width'];
                }
                if (is_string($field['placeholder_en'] ?? null) && $field['placeholder_en'] !== '') {
                    $merged['placeholder_en'] = $field['placeholder_en'];
                }
                if (is_string($field['placeholder_ar'] ?? null) && $field['placeholder_ar'] !== '') {
                    $merged['placeholder_ar'] = $field['placeholder_ar'];
                }
                $result[] = $merged;

                continue;
            }

            $result[] = $field;
        }

        foreach (self::definitions() as $definition) {
            if (! isset($seen[$definition['key']])) {
                $result[] = $definition;
            }
        }

        return $result;
    }

    /** @param list<array<string, mixed>> $fields */
    public static function assertPresent(array $fields): void
    {
        $byKey = [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            $key = (string) ($field['key'] ?? '');
            if ($key !== '') {
                $byKey[$key] = $field;
            }
        }

        foreach (self::definitions() as $definition) {
            $actual = $byKey[$definition['key']] ?? null;

            if (! is_array($actual)) {
                throw new InvalidArgumentException('Registration form system fields are missing.');
            }

            foreach (['key', 'type', 'label_en', 'label_ar'] as $property) {
                if (($actual[$property] ?? null) !== $definition[$property]) {
                    throw new InvalidArgumentException('Registration form system fields are invalid.');
                }
            }

            if (($actual['required'] ?? false) !== true || ($actual['visibility'] ?? '') !== 'public') {
                throw new InvalidArgumentException('Registration form system fields must remain required and public.');
            }
        }
    }
}
