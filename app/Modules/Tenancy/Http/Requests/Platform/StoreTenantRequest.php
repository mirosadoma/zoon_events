<?php

namespace App\Modules\Tenancy\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

final class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:tenants,slug'],
            'default_locale' => ['required', 'in:en,ar'],
            'timezone' => ['required', 'timezone'],
            'data_residency_region' => ['required', 'string', 'max:64'],
            'initial_admin_user_id' => ['required', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
