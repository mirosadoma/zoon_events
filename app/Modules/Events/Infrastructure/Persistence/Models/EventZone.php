<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Domain\EventZoneShapeType;
use App\Modules\Events\Domain\EventZoneType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EventZone extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'venue_id',
        'zone_name_en',
        'zone_name_ar',
        'description_en',
        'description_ar',
        'type',
        'capacity',
        'shape_type',
        'polygon_coordinates',
        'shape_radius',
        'shape_rotation',
        'shape_radius_y',
        'label',
        'google_maps_url',
        'lat',
        'lng',
        'fill_color',
        'stroke_color',
        'opacity',
        'stroke_width',
    ];

    protected function casts(): array
    {
        return [
            'type' => EventZoneType::class,
            'shape_type' => EventZoneShapeType::class,
            'capacity' => 'integer',
            'polygon_coordinates' => 'array',
            'shape_radius' => 'float',
            'shape_rotation' => 'float',
            'shape_radius_y' => 'float',
            'lat' => 'float',
            'lng' => 'float',
            'opacity' => 'integer',
            'stroke_width' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(EventVenue::class, 'venue_id');
    }

    public function agendaItems(): HasMany
    {
        return $this->hasMany(EventAgendaItem::class, 'zone_id');
    }
}
