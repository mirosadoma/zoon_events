<?php

namespace App\Modules\Tenancy\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug,
            'status' => $this->status->value, 'default_locale' => $this->default_locale,
            'timezone' => $this->timezone, 'data_residency_region' => $this->data_residency_region,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
