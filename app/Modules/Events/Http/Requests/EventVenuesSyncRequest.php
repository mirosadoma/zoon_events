<?php

namespace App\Modules\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class EventVenuesSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'venues' => ['required', 'array'],
            'venues.*.id' => ['nullable', 'integer'],
            'venues.*.country_id' => ['required', 'integer', 'exists:countries,id'],
            'venues.*.city_id' => ['required', 'integer', 'exists:cities,id'],
            'venues.*.name.en' => ['required', 'string', 'max:255'],
            'venues.*.name.ar' => ['required', 'string', 'max:255'],
            'venues.*.location_address' => ['nullable', 'string', 'max:500'],
            'venues.*.latitude' => ['nullable', 'numeric', 'min:-90', 'max:90'],
            'venues.*.longitude' => ['nullable', 'numeric', 'min:-180', 'max:180'],
            'venues.*.start_at' => ['required', 'date'],
            'venues.*.end_at' => ['required', 'date', 'after:venues.*.start_at'],
            'venues.*.registration_opens_at' => ['required', 'date'],
            'venues.*.registration_closes_at' => ['required', 'date', 'after:venues.*.registration_opens_at'],
        ];
    }
}
