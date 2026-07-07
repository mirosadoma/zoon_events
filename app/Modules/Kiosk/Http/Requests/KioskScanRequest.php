<?php

namespace App\Modules\Kiosk\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class KioskScanRequest extends FormRequest
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
            'credential_id' => ['sometimes', 'string', 'uuid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['qr_payload', 'credential_id'];
            $unknown = array_diff(array_keys($this->all()), $allowed);
            if ($unknown !== []) {
                $validator->errors()->add('body', 'Unknown fields are not permitted.');
            }

            $hasQr = $this->filled('qr_payload');
            $hasCredential = $this->filled('credential_id');

            if (($hasQr && $hasCredential) || (! $hasQr && ! $hasCredential)) {
                $validator->errors()->add('body', 'Exactly one of qr_payload or credential_id must be provided.');
            }
        });
    }
}
