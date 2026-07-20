<?php

namespace App\Modules\VenueMarketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class OpenDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason_code' => ['required', 'string', 'min:1', 'max:80'],
            'reason' => ['required', 'string', 'min:1', 'max:4000'],
        ];
    }

    public function reasonCode(): string
    {
        return (string) $this->validated('reason_code');
    }

    public function reason(): string
    {
        return (string) $this->validated('reason');
    }

    public function idempotencyKey(): string
    {
        return (string) $this->header('Idempotency-Key');
    }
}
