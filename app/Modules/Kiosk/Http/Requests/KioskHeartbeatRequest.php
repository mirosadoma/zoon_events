<?php

namespace App\Modules\Kiosk\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class KioskHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'printer_status' => ['sometimes', 'string', Rule::in(['ready', 'error', 'disconnected', 'unknown'])],
            'printer_reason_code' => ['sometimes', 'nullable', 'string', 'max:120'],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:40'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['printer_status', 'printer_reason_code', 'app_version'];
            $unknown = array_diff(array_keys($this->all()), $allowed);
            if ($unknown !== []) {
                $validator->errors()->add('body', 'Unknown fields are not permitted.');
            }
        });
    }
}
