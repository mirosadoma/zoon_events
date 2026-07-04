<?php

namespace App\Modules\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class EventWriteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:100'],
            'name.en' => ['required', 'string', 'max:160'],
            'name.ar' => ['required', 'string', 'max:160'],
            'description.en' => ['nullable', 'string', 'max:5000'],
            'description.ar' => ['nullable', 'string', 'max:5000'],
            'tier' => ['required', Rule::in(['corporate', 'public', 'vip', 'vvip'])],
            'timezone' => ['required', 'timezone'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'registration_opens_at' => ['required', 'date'],
            'registration_closes_at' => ['required', 'date', 'after:registration_opens_at', 'before_or_equal:end_at'],
            'capacity' => ['required', 'integer', 'min:1'],
            'location_name.en' => ['nullable', 'string', 'max:200'],
            'location_name.ar' => ['nullable', 'string', 'max:200'],
            'location_address.en' => ['nullable', 'string', 'max:500'],
            'location_address.ar' => ['nullable', 'string', 'max:500'],
            'brand_reference' => ['nullable', 'string', 'max:120'],
            'domain_reference' => ['nullable', 'string', 'max:253'],
        ];
    }

    /** @return array<string,mixed> */
    public function attributesForAction(): array
    {
        $data = $this->validated();

        return [
            'slug' => $data['slug'],
            'name_en' => $data['name']['en'],
            'name_ar' => $data['name']['ar'],
            'description_en' => $data['description']['en'] ?? null,
            'description_ar' => $data['description']['ar'] ?? null,
            'tier' => $data['tier'],
            'timezone' => $data['timezone'],
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'registration_opens_at' => $data['registration_opens_at'],
            'registration_closes_at' => $data['registration_closes_at'],
            'capacity' => $data['capacity'],
            'location_name_en' => $data['location_name']['en'] ?? null,
            'location_name_ar' => $data['location_name']['ar'] ?? null,
            'location_address_en' => $data['location_address']['en'] ?? null,
            'location_address_ar' => $data['location_address']['ar'] ?? null,
            'brand_reference' => $data['brand_reference'] ?? null,
            'domain_reference' => $data['domain_reference'] ?? null,
        ];
    }
}
