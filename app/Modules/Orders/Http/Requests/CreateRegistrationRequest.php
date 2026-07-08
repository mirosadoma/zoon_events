<?php

namespace App\Modules\Orders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateRegistrationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'form_version_id' => ['required'],
            'ticket_type_id' => ['required'],
            'buyer' => ['required', 'array:first_name,last_name,email,phone,preferred_locale'],
            'attendee' => ['required', 'array:first_name,last_name,email,phone,preferred_locale'],
            'buyer.first_name' => ['required', 'string', 'max:120'],
            'buyer.last_name' => ['required', 'string', 'max:120'],
            'buyer.email' => ['required', 'email', 'max:254'],
            'buyer.phone' => ['nullable', 'regex:/^\+9665\d{8}$/'],
            'attendee.first_name' => ['required', 'string', 'max:120'],
            'attendee.last_name' => ['required', 'string', 'max:120'],
            'attendee.email' => ['required', 'email', 'max:254'],
            'attendee.phone' => ['nullable', 'regex:/^\+9665\d{8}$/'],
            'answers' => ['required', 'array', 'max:100'],
            'consents' => ['required', 'array:terms,privacy,marketing'],
            'consents.terms' => ['required', 'accepted'],
            'consents.privacy' => ['required', 'accepted'],
            'consents.marketing' => ['required', 'boolean'],
        ];
    }

    /** @return array{first_name:string,last_name:string,email:string,phone?:string} */
    public function safePerson(string $key): array
    {
        $person = $this->validated($key);

        return array_filter([
            'first_name' => trim(strip_tags($person['first_name'])),
            'last_name' => trim(strip_tags($person['last_name'])),
            'email' => mb_strtolower(trim($person['email'])),
            'phone' => isset($person['phone']) ? trim(strip_tags($person['phone'])) : null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
