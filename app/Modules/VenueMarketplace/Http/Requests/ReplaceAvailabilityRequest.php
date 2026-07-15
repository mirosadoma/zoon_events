<?php

namespace App\Modules\VenueMarketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReplaceAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'windows' => ['required', 'array', 'max:365'],
            'windows.*.available_from' => ['required', 'date'],
            'windows.*.available_until' => ['required', 'date', 'after:windows.*.available_from'],
            'windows.*.status' => ['sometimes', Rule::in(['available', 'blocked'])],
            'windows.*.reason_code' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('version');
    }

    public function windowsForAction(): array
    {
        return $this->validated('windows');
    }
}
