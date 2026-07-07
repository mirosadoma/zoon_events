<?php

namespace App\Modules\AccessControl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class AuthorizeGateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'external_acs_lane_id' => ['required', 'string', 'max:160'],
            'direction' => ['required', 'string', Rule::in(['entry', 'exit'])],
            'credential_reference' => ['sometimes', 'string', 'max:512'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['external_acs_lane_id', 'direction', 'credential_reference'];
            $unknown = array_diff(array_keys($this->all()), $allowed);

            if ($unknown !== []) {
                $validator->errors()->add('body', 'Unknown fields are not permitted.');
            }
        });
    }
}
