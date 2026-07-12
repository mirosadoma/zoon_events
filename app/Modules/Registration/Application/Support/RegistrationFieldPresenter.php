<?php

namespace App\Modules\Registration\Application\Support;

use App\Modules\Registration\Domain\Fields\FormFieldChoiceOptions;
use App\Modules\Registration\Domain\Fields\RegistrationSystemFields;

final class RegistrationFieldPresenter
{
    private const CHOICE_TYPES = ['select', 'multi_select', 'radio', 'checkbox'];

    /** @param array<string,mixed> $field */
    public function clientField(array $field, int $index): array
    {
        $type = (string) ($field['type'] ?? 'text');
        $mapped = [
            'key' => (string) ($field['key'] ?? "field_{$index}"),
            'type' => $type,
            'label_en' => (string) ($field['label_en'] ?? ''),
            'label_ar' => (string) ($field['label_ar'] ?? ''),
            'required' => (bool) ($field['required'] ?? false),
            'system' => RegistrationSystemFields::isSystemKey((string) ($field['key'] ?? '')),
        ];

        if (in_array($type, self::CHOICE_TYPES, true)) {
            $mapped['options'] = $this->publicOptions($field['options'] ?? []);
        }

        return $mapped;
    }

    /** @param array<string,mixed> $field */
    public function builderField(array $field, int $index): array
    {
        $type = (string) ($field['type'] ?? 'text');
        $mapped = [
            'key' => (string) ($field['key'] ?? "field_{$index}"),
            'type' => $type,
            'label_en' => (string) ($field['label_en'] ?? ''),
            'label_ar' => (string) ($field['label_ar'] ?? ''),
            'required' => (bool) ($field['required'] ?? false),
            'system' => RegistrationSystemFields::isSystemKey((string) ($field['key'] ?? '')),
        ];

        if (in_array($type, self::CHOICE_TYPES, true)) {
            $mapped['options'] = $this->builderOptions($field['options'] ?? []);
        }

        return $mapped;
    }

    /** @return list<array{value:string,label_en:string,label_ar:string}> */
    private function publicOptions(mixed $options): array
    {
        return FormFieldChoiceOptions::normalizeForStorage(is_array($options) ? $options : []);
    }

    /** @return list<array{id:string,label_en:string,label_ar:string}> */
    private function builderOptions(mixed $options): array
    {
        return array_map(
            static fn (array $option): array => [
                'id' => $option['value'],
                'label_en' => $option['label_en'],
                'label_ar' => $option['label_ar'],
            ],
            $this->publicOptions($options),
        );
    }
}
