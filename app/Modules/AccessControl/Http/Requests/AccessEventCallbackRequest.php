<?php

namespace App\Modules\AccessControl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class AccessEventCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'external_event_id' => ['required', 'string', 'max:160'],
            'external_acs_lane_id' => ['required', 'string', 'max:160'],
            'credential_reference' => ['sometimes', 'nullable', 'string', 'max:512'],
            'event_type' => ['required', 'string', Rule::in(['entry', 'exit'])],
            'occurred_at' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['external_event_id', 'external_acs_lane_id', 'credential_reference', 'event_type', 'occurred_at'];
            $unknown = array_diff(array_keys($this->all()), $allowed);
            if ($unknown !== []) {
                $validator->errors()->add('body', 'Unknown fields are not permitted.');
            }
        });
    }
}
