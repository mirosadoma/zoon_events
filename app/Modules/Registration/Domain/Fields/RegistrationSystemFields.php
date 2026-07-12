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
     * @param  list<array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    public static function enforce(array $fields): array
    {
        $custom = [];

        foreach ($fields as $field) {
            $key = (string) ($field['key'] ?? '');
            if (! self::isSystemKey($key)) {
                $custom[] = $field;
            }
        }

        return array_merge(self::definitions(), $custom);
    }

    /** @param list<array<string, mixed>> $fields */
    public static function assertPresent(array $fields): void
    {
        foreach (self::definitions() as $index => $definition) {
            $actual = $fields[$index] ?? null;

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
