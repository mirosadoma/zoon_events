<?php

namespace App\Modules\BadgePrinting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class PreviewBadgePrintJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'attendee_id' => ['required', 'string'],
            'credential_id' => ['required', 'string'],
            'field_overrides' => ['sometimes', 'array'],
            'field_overrides.job_title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'field_overrides.custom_text' => ['sometimes', 'nullable', 'string', 'max:200'],
            'field_overrides.company' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['attendee_id', 'credential_id', 'field_overrides'];
            $unknown = array_diff(array_keys($this->all()), $allowed);
            if ($unknown !== []) {
                $validator->errors()->add('body', 'Unknown fields are not permitted.');
            }
        });
    }

    /** @return array<string, string|null> */
    public function fieldOverrides(): array
    {
        $raw = $this->input('field_overrides', []);
        if (! is_array($raw)) {
            return [];
        }

        $allowed = ['job_title', 'custom_text', 'company'];
        $overrides = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $raw)) {
                continue;
            }
            $value = $raw[$key];
            $overrides[$key] = is_string($value) || is_numeric($value) ? trim((string) $value) : null;
        }

        return $overrides;
    }
}
