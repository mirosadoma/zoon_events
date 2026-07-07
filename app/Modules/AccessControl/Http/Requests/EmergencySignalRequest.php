<?php

namespace App\Modules\AccessControl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class EmergencySignalRequest extends FormRequest
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
            'external_acs_zone_id' => ['sometimes', 'nullable', 'string'],
            'signal_source' => ['sometimes', 'string', Rule::in(['acs', 'fire_alarm', 'system'])],
            'occurred_at' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['action', 'external_acs_zone_id', 'signal_source', 'occurred_at'];
            $unknown = array_diff(array_keys($this->all()), $allowed);
            if ($unknown !== []) {
                $validator->errors()->add('body', 'Unknown fields are not permitted.');
            }
        });
    }
}
