<?php

namespace App\Modules\VenueMarketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReviseStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dispute_public_id' => ['required', 'string', 'size:26'],
            'reason_code' => ['required', 'string', 'min:1', 'max:80'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.asset_public_id' => ['required', 'string', 'size:26'],
            'lines.*.unit_price_minor' => ['required', 'integer', 'min:0'],
            'lines.*.billable_units' => ['required', 'integer', 'min:1'],
        ];
    }

    public function idempotencyKey(): string
    {
        return (string) $this->header('Idempotency-Key');
    }
}
