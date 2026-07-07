<?php

namespace App\Modules\Attendees\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterWalkUpAttendeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendee.walkup.register');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ticket_type_id' => ['required', 'uuid'],
            'form_version_id' => ['required', 'uuid'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string', 'max:10'],
            'answers' => ['sometimes', 'array'],
            'consent' => ['sometimes', 'array'],
            'buyer' => ['required', 'array'],
            'buyer.first_name' => ['required', 'string', 'max:255'],
            'buyer.last_name' => ['required', 'string', 'max:255'],
            'buyer.email' => ['required', 'email', 'max:255'],
            'buyer.phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'attendee' => ['required', 'array'],
            'attendee.first_name' => ['required', 'string', 'max:255'],
            'attendee.last_name' => ['required', 'string', 'max:255'],
            'attendee.email' => ['required', 'email', 'max:255'],
            'attendee.phone' => ['sometimes', 'nullable', 'string', 'max:30'],
        ];
    }
}
