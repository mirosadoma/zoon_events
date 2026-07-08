<?php

namespace App\Modules\Ticketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PriceTierWriteRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:120'],
            'price_minor' => [$required, 'integer', 'min:0'],
            'currency' => [$required, 'regex:/^[A-Z]{3}$/'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'remaining_at_most' => ['nullable', 'integer', 'min:1'],
            'priority' => [$required, 'integer', 'min:1'],
            'status' => ['sometimes', 'string', 'in:draft,active,retired'],
        ];
    }

    protected function withValidator($validator): void
    {
        if (! $this->isMethod('POST')) {
            return;
        }

        $validator->after(function ($validator): void {
            if ($this->input('starts_at') === null && $this->input('ends_at') === null && $this->input('remaining_at_most') === null) {
                $validator->errors()->add('starts_at', 'At least one time or capacity selector is required.');
            }
        });
    }
}
