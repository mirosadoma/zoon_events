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
            'qr_payload' => ['sometimes', 'string', 'max:512'],
            'credential_id' => ['sometimes', 'string'],
            'scanner_type' => ['required', 'string', Rule::in(['staff_phone', 'handheld_scanner', 'manual_desk'])],
            'override' => ['sometimes', 'boolean'],
            'override_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'offline_mode' => ['sometimes', 'boolean'],
            'scanned_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['qr_payload', 'credential_id', 'scanner_type', 'override', 'override_reason', 'offline_mode', 'scanned_at'];
            $unknown = array_diff(array_keys($this->all()), $allowed);
            if ($unknown !== []) {
                $validator->errors()->add('body', 'Unknown fields are not permitted.');
            }

            $hasQr = $this->filled('qr_payload');
            $hasCredential = $this->filled('credential_id');

            if (! $hasQr && ! $hasCredential) {
                $validator->errors()->add('body', 'Exactly one of qr_payload or credential_id must be provided.');
            }

            if ($hasQr && $hasCredential) {
                $validator->errors()->add('body', 'Provide only one of qr_payload or credential_id, not both.');
            }
        });
    }
}
