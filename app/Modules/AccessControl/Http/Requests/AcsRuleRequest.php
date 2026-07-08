<?php

namespace App\Modules\AccessControl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class AcsRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ticket_type_id' => ['sometimes', 'nullable', 'integer'],
            'attendee_type' => ['sometimes', 'nullable', 'string', Rule::in(['attendee', 'staff', 'vip', 'vendor'])],
            'zone_id' => ['required', 'integer'],
            'lane_id' => ['sometimes', 'nullable', 'integer'],
            'access_direction' => ['required', 'string', Rule::in(['entry', 'exit', 'bidirectional'])],
            'anti_passback_exempt' => ['sometimes', 'boolean'],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_until' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['ticket_type_id', 'attendee_type', 'zone_id', 'lane_id', 'access_direction', 'anti_passback_exempt', 'valid_from', 'valid_until', 'status'];
            $unknown = array_diff(array_keys($this->all()), $allowed);
            if ($unknown !== []) {
                $validator->errors()->add('body', 'Unknown fields are not permitted.');
            }
        });
    }
}
