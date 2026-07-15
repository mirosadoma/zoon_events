<?php

namespace App\Modules\VenueMarketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CatalogSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'string', 'max:2048'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'venue_public_id' => ['nullable', 'string', 'size:26'],
            'country_code' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'city_code' => ['nullable', 'string', 'max:80'],
            'asset_type' => ['nullable', 'string', 'in:turnstile,security_gate,camera,kiosk,printer,scanner,access_lane,access_zone'],
            'capability' => ['nullable', 'string', 'max:80'],
            'minimum_capacity_per_minute' => ['nullable', 'integer', 'min:1'],
            'maximum_price_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'requested_start_at' => ['nullable', 'date', 'required_with:requested_end_at'],
            'requested_end_at' => ['nullable', 'date', 'after:requested_start_at', 'required_with:requested_start_at'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return $this->safe()->except(['cursor', 'page_size']);
    }
}
