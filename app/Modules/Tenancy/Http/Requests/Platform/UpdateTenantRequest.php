<?php

namespace App\Modules\Tenancy\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'status' => ['sometimes', 'in:active,suspended,deactivated'],
            'default_locale' => ['sometimes', 'in:en,ar'],
            'timezone' => ['sometimes', 'timezone'],
            'data_residency_region' => ['sometimes', 'string', 'max:64'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
