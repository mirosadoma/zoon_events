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
            'tier' => ['nullable', Rule::in(['corporate', 'public', 'vip', 'vvip'])],
            'timezone' => ['required', 'string', 'max:64', Rule::exists('timezones', 'identifier')],
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
            'venues' => ['nullable', 'array'],
            'venues.*.id' => ['nullable', 'integer'],
            'venues.*.country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'venues.*.city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'venues.*.name.en' => ['required_with:venues', 'string', 'max:160'],
            'venues.*.name.ar' => ['required_with:venues', 'string', 'max:160'],
            'venues.*.location_address' => ['nullable', 'string', 'max:500'],
            'venues.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'venues.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'venues.*.start_at' => ['nullable', 'date'],
            'venues.*.end_at' => ['nullable', 'date'],
            'venues.*.registration_opens_at' => ['nullable', 'date'],
            'venues.*.registration_closes_at' => ['nullable', 'date'],
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
            'tier' => $data['tier'] ?? 'public',
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
            'venues' => collect($data['venues'] ?? [])->map(fn (array $venue): array => [
                'id' => $venue['id'] ?? null,
                'country_id' => $venue['country_id'] ?? null,
                'city_id' => $venue['city_id'] ?? null,
                'name_en' => $venue['name']['en'],
                'name_ar' => $venue['name']['ar'],
                'location_address' => $venue['location_address'] ?? null,
                'latitude' => $venue['latitude'] ?? null,
                'longitude' => $venue['longitude'] ?? null,
                'start_at' => $venue['start_at'] ?? null,
                'end_at' => $venue['end_at'] ?? null,
                'registration_opens_at' => $venue['registration_opens_at'] ?? null,
                'registration_closes_at' => $venue['registration_closes_at'] ?? null,
            ])->all(),
        ];
    }
}
