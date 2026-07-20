<?php

namespace App\Modules\VenueMarketplace\Http\Requests;

final class UpdateVenueRequest extends CreateVenueRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    public function expectedVersion(): int
    {
        return (int) $this->validated('version');
    }
}
