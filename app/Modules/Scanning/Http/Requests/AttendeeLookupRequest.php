<?php

namespace App\Modules\Scanning\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class AttendeeLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'qr_payload'        => ['sometimes', 'string', 'max:512'],
            'query'             => ['sometimes', 'string', 'min:2', 'max:120'],
            'confirmation_code' => ['sometimes', 'string', 'max:12'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['qr_payload', 'query', 'confirmation_code'];
            $unknown = array_diff(array_keys($this->all()), $allowed);
            if ($unknown !== []) {
                $validator->errors()->add('body', 'Unknown fields are not permitted.');
            }

            $hasQr    = $this->filled('qr_payload');
            $hasQuery = $this->filled('query');

            if (! $hasQr && ! $hasQuery) {
                $validator->errors()->add('body', 'Exactly one of qr_payload or query must be provided.');
            }

            if ($hasQr && $hasQuery) {
                $validator->errors()->add('body', 'Provide only one of qr_payload or query, not both.');
            }
        });
    }
}
