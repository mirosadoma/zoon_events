<?php

namespace App\Modules\AccessControl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateAcsZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'anti_passback_enabled' => ['sometimes', 'boolean'],
            'unavailability_mode' => ['sometimes', 'string', Rule::in(['fail_open', 'fail_closed'])],
            'emergency_egress_mode' => ['sometimes', 'string', Rule::in(['fail_open', 'fail_closed'])],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['name', 'anti_passback_enabled', 'unavailability_mode', 'emergency_egress_mode', 'status'];
            $unknown = array_diff(array_keys($this->all()), $allowed);
            if ($unknown !== []) {
                $validator->errors()->add('body', 'Unknown fields are not permitted.');
            }
        });
    }
}
