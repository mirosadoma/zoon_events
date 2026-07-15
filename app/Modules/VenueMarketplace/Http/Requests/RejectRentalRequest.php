<?php

namespace App\Modules\VenueMarketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RejectRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('expected_version');
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
