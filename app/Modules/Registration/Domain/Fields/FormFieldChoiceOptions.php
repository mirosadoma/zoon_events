<?php

namespace App\Modules\Registration\Domain\Fields;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class FormFieldChoiceOptions
{
    /** @param list<mixed> $options @return list<array{value:string,label_en:string,label_ar:string}> */
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

            $normalized[] = [
                'value' => $value,
                'label_en' => $labelEn,
                'label_ar' => $labelAr,
            ];
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
}
