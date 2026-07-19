<?php

namespace App\Modules\BadgePrinting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReprintBadgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reprint_reason' => ['required', 'string', 'min:1', 'max:500'],
            'field_overrides' => ['sometimes', 'array'],
            'field_overrides.job_title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'field_overrides.custom_text' => ['sometimes', 'nullable', 'string', 'max:200'],
            'field_overrides.company' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->isJson()) {
            $allowed = ['reprint_reason', 'field_overrides'];
            $this->replace(collect($this->all())->only($allowed)->all());
        }
    }

    /** @return array<string, string|null> */
    public function fieldOverrides(): array
    {
        $raw = $this->input('field_overrides', []);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach (['job_title', 'custom_text', 'company'] as $key) {
            if (! array_key_exists($key, $raw)) {
                continue;
            }
            $value = $raw[$key];
            $out[$key] = $value === null ? null : (string) $value;
        }

        return $out;
    }
}
