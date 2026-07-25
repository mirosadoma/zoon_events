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
            'width' => in_array(($width = $field['width'] ?? 'full'), ['full', 'half', 'third'], true)
                ? $width
                : 'full',
        ];

        if (isset($field['content']) && is_string($field['content'])) {
            $mapped['content'] = $field['content'];
        }

        $choiceStyle = (string) ($field['choice_style'] ?? '');
        if (in_array($choiceStyle, ['square', 'circle', 'toggle', 'pill', 'card', 'button'], true)) {
            $mapped['choice_style'] = $choiceStyle;
        }

        $choiceColor = (string) ($field['choice_color'] ?? '');
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $choiceColor) === 1) {
            $mapped['choice_color'] = strtoupper($choiceColor);
        }

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
            'width' => $field['width'] ?? 'full',
            'placeholder_en' => $field['placeholder_en'] ?? '',
            'placeholder_ar' => $field['placeholder_ar'] ?? '',
            'content' => $field['content'] ?? '',
            'choice_style' => $field['choice_style'] ?? null,
            'choice_color' => $field['choice_color'] ?? null,
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
