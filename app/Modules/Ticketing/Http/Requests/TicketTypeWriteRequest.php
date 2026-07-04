<?php

namespace App\Modules\Ticketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TicketTypeWriteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'regex:/^[A-Z0-9_-]{2,40}$/'],
            'name.en' => ['required', 'string', 'max:160'],
            'name.ar' => ['required', 'string', 'max:160'],
            'description.en' => ['nullable', 'string', 'max:5000'],
            'description.ar' => ['nullable', 'string', 'max:5000'],
            'attendee_type' => ['nullable', 'string', 'max:80'],
            'capacity' => ['required', 'integer', 'min:1'],
            'price_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'sale_starts_at' => ['required', 'date'],
            'sale_ends_at' => ['required', 'date', 'after:sale_starts_at'],
        ];
    }

    /** @return array<string,mixed> */
    public function attributesForAction(): array
    {
        $data = $this->validated();

        return [
            'code' => $data['code'],
            'name_en' => $data['name']['en'],
            'name_ar' => $data['name']['ar'],
            'description_en' => $data['description']['en'] ?? null,
            'description_ar' => $data['description']['ar'] ?? null,
            'attendee_type' => $data['attendee_type'] ?? 'general',
            'capacity' => $data['capacity'],
            'base_price_minor' => $data['price_minor'],
            'currency' => $data['currency'],
            'sale_starts_at' => $data['sale_starts_at'],
            'sale_ends_at' => $data['sale_ends_at'],
            'status' => 'active',
        ];
    }
}
