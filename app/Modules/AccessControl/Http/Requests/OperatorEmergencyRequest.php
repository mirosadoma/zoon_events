<?php

namespace App\Modules\AccessControl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class OperatorEmergencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['raise', 'clear'])],
            'zone_id' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['action', 'zone_id'];
            $unknown = array_diff(array_keys($this->all()), $allowed);
            if ($unknown !== []) {
                $validator->errors()->add('body', 'Unknown fields are not permitted.');
            }
        });
    }
}
