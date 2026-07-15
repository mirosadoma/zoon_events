<?php

namespace App\Modules\VenueMarketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarketplaceQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'min:1'],
            'publication_public_ids' => ['required', 'array', 'min:1', 'max:100'],
            'publication_public_ids.*' => ['required', 'string', 'size:26', 'distinct'],
            'requested_start_at' => ['required', 'date'],
            'requested_end_at' => ['required', 'date', 'after:requested_start_at'],
        ];
    }

    /** @return list<string> */
    public function publicationPublicIds(): array
    {
        return array_values($this->validated('publication_public_ids'));
    }
}
