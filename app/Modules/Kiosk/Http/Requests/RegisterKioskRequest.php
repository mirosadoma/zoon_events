<?php

namespace App\Modules\Kiosk\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterKioskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'device_name' => ['required', 'string', 'max:120'],
            'location_label' => ['sometimes', 'nullable', 'string', 'max:160'],
            // Always required on create; confirmation_required checkbox removed from UI.
            'confirmation_code' => ['required', 'string', 'max:12'],
            // Accepted but ignored if sent; create path always enables confirmation.
            'confirmation_required' => ['sometimes', 'boolean'],
        ];
    }
}
