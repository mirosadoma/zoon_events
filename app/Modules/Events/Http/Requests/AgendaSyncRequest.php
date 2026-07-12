<?php

namespace App\Modules\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class AgendaSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'max:200'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.title_en' => ['required', 'string', 'max:160'],
            'items.*.title_ar' => ['required', 'string', 'max:160'],
            'items.*.start_at' => ['required', 'date'],
            'items.*.end_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('items', []) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $start = $item['start_at'] ?? null;
                $end = $item['end_at'] ?? null;

                if ($start && $end && strtotime((string) $end) <= strtotime((string) $start)) {
                    $validator->errors()->add("items.{$index}.end_at", 'End time must be after start time.');
                }
            }
        });
    }
}
