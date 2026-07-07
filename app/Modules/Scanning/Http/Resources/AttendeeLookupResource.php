<?php

namespace App\Modules\Scanning\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AttendeeLookupResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = (array) $this->resource;

        return [
            'too_many' => (bool) ($data['too_many'] ?? false),
            'matches' => $data['matches'] ?? [],
        ];
    }
}
