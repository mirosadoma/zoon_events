<?php

namespace App\Modules\BadgePrinting\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class BadgeTemplate extends Model
{
    protected $fillable = [
        'id', 'tenant_id', 'event_id', 'name', 'layout',
        'paper_size', 'printer_type', 'status',
    ];

    protected function casts(): array
    {
        return [
            'layout' => 'array',
        ];
    }
}
