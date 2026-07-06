<?php

namespace App\Modules\Scanning\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SubmitScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'qr_payload' => ['required', 'string', 'max:512'],
            'scanner_type' => ['required', 'string', Rule::in(['staff_phone', 'handheld_scanner'])],
            'override' => ['sometimes', 'boolean'],
            'override_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'offline_mode' => ['sometimes', 'boolean'],
            'scanned_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['qr_payload', 'scanner_type', 'override', 'override_reason', 'offline_mode', 'scanned_at'];
            $unknown = array_diff(array_keys($this->all()), $allowed);
            if ($unknown !== []) {
                $validator->errors()->add('body', 'Unknown fields are not permitted.');
            }
        });
    }
}
