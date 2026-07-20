<?php

namespace App\Modules\BadgePrinting\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class BadgeTemplate extends Model
{
    protected $fillable = [
        'id', 'tenant_id', 'event_id', 'name', 'layout',
        'paper_size', 'printer_type', 'status',
        'background_color', 'background_gradient', 'background_image_path', 'orientation',
        'canvas_width', 'canvas_height',
    ];

    protected function casts(): array
    {
        return [
            'layout' => 'array',
            'background_gradient' => 'array',
        ];
    }
}
