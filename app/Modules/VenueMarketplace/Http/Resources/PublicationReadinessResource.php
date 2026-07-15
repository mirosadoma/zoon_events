<?php

namespace App\Modules\VenueMarketplace\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PublicationReadinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : [];

        return [
            'ready' => (bool) ($data['ready'] ?? false),
            'reason_code' => $data['reason_code'] ?? null,
            'missing' => array_values(array_filter(
                $data['missing'] ?? [],
                static fn (mixed $value): bool => is_string($value) && $value !== '',
            )),
        ];
    }
}
