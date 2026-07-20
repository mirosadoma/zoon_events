<?php

namespace App\Modules\VenueMarketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:160'],
            'name_ar' => ['required', 'string', 'max:160'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'address_en' => ['required', 'string', 'max:500'],
            'address_ar' => ['required', 'string', 'max:500'],
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'city_code' => ['required', 'string', 'max:80'],
            'timezone' => ['required', 'string', 'max:64', 'timezone:all'],
            'business_contact_name' => ['nullable', 'string', 'max:160'],
            'business_contact_email' => ['nullable', 'email:rfc', 'max:254'],
            'business_contact_phone' => ['nullable', 'string', 'max:32'],
            'publish_contact' => ['sometimes', 'boolean'],
        ];
    }

    public function attributesForAction(): array
    {
        return $this->safe()->except(['version']);
    }
}
