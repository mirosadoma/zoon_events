<?php

namespace App\Modules\Registration\Application\Validation;

use App\Modules\Registration\Domain\Fields\FormFieldType;
use InvalidArgumentException;

final class FormSchemaValidator
{
    private const RESERVED_KEYS = [
        'id', 'tenant_id', 'event_id', 'order_id', 'payment', 'credential', 'status',
    ];

    /** @param list<array<string,mixed>> $fields */
    public function validate(array $fields): void
    {
        if ($fields === [] || count($fields) > (int) config('registration.max_form_fields', 100)) {
            throw new InvalidArgumentException('Registration form field count is invalid.');
        }

        $seen = [];
        foreach ($fields as $position => $field) {
            $key = (string) ($field['key'] ?? '');
            if (! preg_match('/^[a-z][a-z0-9_]{1,63}$/', $key) || in_array($key, self::RESERVED_KEYS, true) || isset($seen[$key])) {
                throw new InvalidArgumentException('Registration form contains an invalid field key.');
            }
            $type = FormFieldType::from((string) ($field['type'] ?? ''));
            if (trim((string) ($field['label_en'] ?? '')) === '' || trim((string) ($field['label_ar'] ?? '')) === '') {
                throw new InvalidArgumentException('Registration fields require Arabic and English labels.');
            }
            $visibility = (string) ($field['visibility'] ?? 'public');
            if (! in_array($visibility, ['public', 'internal'], true)) {
                throw new InvalidArgumentException('Registration field visibility is invalid.');
            }
            if (($visibility === 'internal' || $type === FormFieldType::Hidden)
                && array_key_exists('default', $field)
                && ! is_scalar($field['default'])
                && $field['default'] !== null) {
                throw new InvalidArgumentException('Server-owned field defaults must be scalar.');
            }
            if (in_array($type, [FormFieldType::Select, FormFieldType::Dropdown, FormFieldType::MultiSelect], true)) {
                $options = $field['options'] ?? [];
                if (! is_array($options) || $options === [] || count($options) > 100) {
                    throw new InvalidArgumentException('Choice fields require bounded options.');
                }
            }
            $rules = $field['validation'] ?? [];
            if (! is_array($rules) || array_diff(array_keys($rules), ['min', 'max', 'max_length']) !== []) {
                throw new InvalidArgumentException('Registration field validation rules are invalid.');
            }
            if (isset($rules['max_length']) && (! is_int($rules['max_length']) || $rules['max_length'] < 1 || $rules['max_length'] > 5000)) {
                throw new InvalidArgumentException('Registration field maximum length is invalid.');
            }
            if (isset($rules['min'], $rules['max'])
                && (! is_numeric($rules['min']) || ! is_numeric($rules['max']) || $rules['min'] > $rules['max'])) {
                throw new InvalidArgumentException('Registration field numeric bounds are invalid.');
            }

            $condition = $field['condition'] ?? null;
            if (is_array($condition)) {
                $dependsOn = (string) ($condition['field'] ?? '');
                if (! isset($seen[$dependsOn]) || $seen[$dependsOn] >= $position) {
                    throw new InvalidArgumentException('Conditional fields may reference only an earlier field.');
                }
                if (! in_array((string) ($condition['operator'] ?? ''), ['equals', 'not_equals', 'in'], true)) {
                    throw new InvalidArgumentException('Conditional field operator is invalid.');
                }
            }

            $seen[$key] = $position;
        }
    }

    /** @param list<array<string,mixed>> $fields */
    public function canonicalHash(array $fields): string
    {
        $this->validate($fields);

        return hash('sha256', json_encode($this->sortRecursively($fields), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function sortRecursively(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map($this->sortRecursively(...), $value);
        }
        ksort($value);

        return array_map($this->sortRecursively(...), $value);
    }
}
