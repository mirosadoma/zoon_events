<?php

namespace App\Modules\AccessControl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class AcsLaneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'zone_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'external_acs_lane_id' => ['required', 'string', 'max:160'],
            'gate_type' => ['required', 'string', Rule::in(['turnstile', 'door', 'speedgate', 'manual'])],
            'access_direction' => ['required', 'string', Rule::in(['entry', 'exit', 'bidirectional'])],
            'is_admission_lane' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['zone_id', 'name', 'external_acs_lane_id', 'gate_type', 'access_direction', 'is_admission_lane', 'status'];
            $unknown = array_diff(array_keys($this->all()), $allowed);
            if ($unknown !== []) {
                $validator->errors()->add('body', 'Unknown fields are not permitted.');
            }
        });
    }
}
