<?php

namespace App\Modules\VenueMarketplace\Http\Requests;

final class UpdateVenueAssetRequest extends CreateVenueAssetRequest
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
