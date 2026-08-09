<?php

namespace App\Modules\Registration\Domain\Fields;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class FormFieldChoiceOptions
{
    public const LINKED_TEXT_SUFFIX = '__linked_text';

    /**
     * @param  list<mixed>  $options
     * @return list<array{value:string,label_en:string,label_ar:string,linked_text?:bool}>
     */
    public static function normalizeForStorage(array $options): array
    {
        $normalized = [];

        foreach ($options as $option) {
            if (is_string($option) && $option !== '') {
                $normalized[] = [
                    'value' => $option,
                    'label_en' => $option,
                    'label_ar' => $option,
                ];

                continue;
            }

            if (! is_array($option)) {
                continue;
            }

            $labelEn = trim((string) ($option['label_en'] ?? ''));
            $labelAr = trim((string) ($option['label_ar'] ?? ''));
            $value = trim((string) ($option['value'] ?? $option['id'] ?? ''));

            if ($value === '') {
                $value = (string) Str::uuid();
            }

            $row = [
                'value' => $value,
                'label_en' => $labelEn,
                'label_ar' => $labelAr,
            ];

            if (filter_var($option['linked_text'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $row['linked_text'] = true;
            }

            $normalized[] = $row;
        }

        return $normalized;
    }

    /** @param list<mixed> $options */
    public static function validate(array $options): void
    {
        if ($options === [] || count($options) > 100) {
            throw new InvalidArgumentException('Choice fields require bounded options.');
        }

        $seen = [];
        foreach ($options as $option) {
            if (is_string($option)) {
                if ($option === '' || isset($seen[$option])) {
                    throw new InvalidArgumentException('Choice field options are invalid.');
                }
                $seen[$option] = true;

                continue;
            }

            if (! is_array($option)) {
                throw new InvalidArgumentException('Choice field options are invalid.');
            }

            $value = trim((string) ($option['value'] ?? $option['id'] ?? ''));
            if ($value === '' || mb_strlen($value) > 64 || isset($seen[$value])) {
                throw new InvalidArgumentException('Choice field options are invalid.');
            }
            if (trim((string) ($option['label_en'] ?? '')) === '' || trim((string) ($option['label_ar'] ?? '')) === '') {
                throw new InvalidArgumentException('Choice field options require Arabic and English labels.');
            }
            if (str_contains($value, self::LINKED_TEXT_SUFFIX)) {
                throw new InvalidArgumentException('Choice field option values are invalid.');
            }

            $seen[$value] = true;
        }
    }

    /** @param list<mixed> $options @return list<string> */
    public static function values(array $options): array
    {
        $values = [];
        foreach ($options as $option) {
            if (is_string($option)) {
                $values[] = $option;

                continue;
            }
            if (is_array($option)) {
                $values[] = (string) ($option['value'] ?? $option['id'] ?? '');
            }
        }

        return $values;
    }

    /**
     * Option values that should reveal a free-text input when selected.
     *
     * @param  list<mixed>  $options
     * @return list<string>
     */
    public static function linkedTextValues(array $options): array
    {
        $values = [];
        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }
            if (! filter_var($option['linked_text'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }
            $value = trim((string) ($option['value'] ?? $option['id'] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    public static function linkedTextAnswerKey(string $fieldKey, string $optionValue): string
    {
        return $fieldKey.'__'.$optionValue.self::LINKED_TEXT_SUFFIX;
    }

    public static function isLinkedTextAnswerKey(string $key): bool
    {
        return str_ends_with($key, self::LINKED_TEXT_SUFFIX);
    }

    public static function optionIsSelected(mixed $answer, string $optionValue): bool
    {
        if (is_string($answer)) {
            return $answer === $optionValue;
        }

        if (is_array($answer)) {
            return in_array($optionValue, array_map(static fn ($item): string => (string) $item, $answer), true);
        }

        return false;
    }
}
