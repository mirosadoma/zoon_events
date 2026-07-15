<?php

namespace App\Modules\VenueMarketplace\Http\Requests;

final class SubmitRentalRequest extends MarketplaceQuoteRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'quote_digest' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'quote_version' => ['required', 'integer', 'min:1'],
        ];
    }

    public function idempotencyKey(): string
    {
        return (string) $this->header('Idempotency-Key');
    }
}
