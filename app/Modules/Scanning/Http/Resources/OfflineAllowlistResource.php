<?php

namespace App\Modules\Scanning\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin array{issued_at:\DateTimeInterface,expires_at:\DateTimeInterface,entries_digest:string,entries:list<array{credential_reference_digest:string}>} */
final class OfflineAllowlistResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'issued_at' => $this->resource['issued_at']->format(DATE_ATOM),
            'expires_at' => $this->resource['expires_at']->format(DATE_ATOM),
            'entries_digest' => $this->resource['entries_digest'],
            'entries' => $this->resource['entries'],
        ];
    }
}
