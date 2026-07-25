<?php

namespace App\Modules\BadgePrinting\Http\Resources;

use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'id' => (string) $this->id,
            'event_id' => (string) $this->event_id,
            'tenant_id' => (string) $this->tenant_id,
            'name' => $this->name,
            'layout' => $this->layout,
            'paper_size' => $this->paper_size,
            'printer_type' => $this->printer_type,
            'orientation' => $this->orientation ?? 'portrait',
            'background_color' => $this->background_color,
            'background_gradient' => $this->background_gradient,
            'background_image_path' => $this->background_image_path,
            'background_image_url' => $this->backgroundImageUrl(),
            'canvas_width' => $this->canvas_width,
            'canvas_height' => $this->canvas_height,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function backgroundImageUrl(): ?string
    {
        $path = $this->background_image_path;
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $url = Storage::disk('public')->url($path);

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : url($url);
    }
}
