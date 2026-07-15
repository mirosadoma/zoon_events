<?php

namespace App\Modules\VenueMarketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateVenueAssetRequest extends FormRequest
{
    private const TYPES = [
        'turnstile', 'security_gate', 'camera', 'kiosk',
        'printer', 'scanner', 'access_lane', 'access_zone',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_type' => ['required', Rule::in(self::TYPES)],
            'name_en' => ['required', 'string', 'max:160'],
            'name_ar' => ['required', 'string', 'max:160'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'location_en' => ['required', 'string', 'max:240'],
            'location_ar' => ['required', 'string', 'max:240'],
            'capabilities' => ['required', 'array', 'max:30'],
            'capabilities.*' => ['required', 'string', 'max:80', 'distinct'],
            'capacity_per_minute' => ['nullable', 'integer', 'min:1'],
            'operational_status' => ['required', Rule::in(['draft', 'active', 'maintenance', 'offline', 'retired'])],
            'pricing_model' => ['required', Rule::in(['per_hour', 'per_day', 'per_rental'])],
            'price_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'binding' => ['nullable', 'array:control_family,adapter_key,external_reference,status'],
            'binding.control_family' => ['nullable', Rule::in(['acs', 'kiosk', 'printer', 'scanner', 'catalog_only'])],
            'binding.adapter_key' => ['nullable', 'string', 'max:80'],
            'binding.external_reference' => ['nullable', 'string', 'max:500'],
            'binding.status' => ['nullable', Rule::in(['active', 'disabled', 'invalid'])],
            'binding.secret_reference' => ['prohibited'],
            'binding.password' => ['prohibited'],
            'binding.credential' => ['prohibited'],
            'binding.token' => ['prohibited'],
        ];
    }

    public function attributesForAction(): array
    {
        return $this->safe()->except(['binding', 'version']);
    }

    public function bindingForAction(): array
    {
        $binding = $this->validated('binding', []);
        if ($binding === [] && $this->validated('asset_type') === 'camera') {
            return ['control_family' => 'catalog_only', 'status' => 'active'];
        }

        return array_filter([
            'control_family' => $binding['control_family'] ?? null,
            'adapter_key' => $binding['adapter_key'] ?? null,
            'opaque_reference' => $binding['external_reference'] ?? null,
            'status' => $binding['status'] ?? 'active',
        ], static fn (mixed $value): bool => $value !== null);
    }
}
