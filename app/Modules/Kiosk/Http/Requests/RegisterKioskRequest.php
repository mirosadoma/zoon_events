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
            'device_name'         => ['required', 'string', 'max:120'],
            'location_label'      => ['sometimes', 'nullable', 'string', 'max:160'],
            'confirmation_required' => ['sometimes', 'boolean'],
            'confirmation_code'   => ['sometimes', 'required_if:confirmation_required,true', 'string', 'max:12'],
        ];
    }
}
