<?php

namespace App\Modules\BadgePrinting\Http\Resources;

use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BadgeTemplate
 */
final class BadgeTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'event_id'     => $this->event_id,
            'tenant_id'    => $this->tenant_id,
            'name'         => $this->name,
            'layout'       => $this->layout,
            'paper_size'   => $this->paper_size,
            'printer_type' => $this->printer_type,
            'status'       => $this->status,
            'created_at'   => $this->created_at?->toIso8601String(),
            'updated_at'   => $this->updated_at?->toIso8601String(),
        ];
    }
}
