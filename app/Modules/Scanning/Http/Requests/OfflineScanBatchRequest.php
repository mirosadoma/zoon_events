<?php

namespace App\Modules\Scanning\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class OfflineScanBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'device_reference' => ['required', 'string', 'max:120'],
            'scans' => ['required', 'array', 'min:1', 'max:500'],
            'scans.*.qr_payload' => ['required', 'string', 'max:512'],
            'scans.*.scanned_at' => ['required', 'date'],
            'scans.*.override' => ['sometimes', 'boolean'],
            'scans.*.override_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['device_reference', 'scans'];
            $unknown = array_diff(array_keys($this->all()), $allowed);
            if ($unknown !== []) {
                $validator->errors()->add('body', 'Unknown fields are not permitted.');
            }

            foreach ($this->input('scans', []) as $index => $scan) {
                if (! is_array($scan)) {
                    continue;
                }

                $allowedScanFields = ['qr_payload', 'scanned_at', 'override', 'override_reason'];
                $unknownScanFields = array_diff(array_keys($scan), $allowedScanFields);
                if ($unknownScanFields !== []) {
                    $validator->errors()->add("scans.{$index}", 'Unknown fields are not permitted.');
                }
            }
        });
    }
}
