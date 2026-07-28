<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventPath extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'venue_id',
        'name_en',
        'name_ar',
        'polyline_coordinates',
        'coordinate_space',
        'from_zone_id',
        'to_zone_id',
        'stroke_color',
        'stroke_width',
        'opacity',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'polyline_coordinates' => 'array',
            'from_zone_id' => 'integer',
            'to_zone_id' => 'integer',
            'stroke_width' => 'integer',
            'opacity' => 'integer',
            'sort_order' => 'integer',
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

    public function fromZone(): BelongsTo
    {
        return $this->belongsTo(EventZone::class, 'from_zone_id');
    }

    public function toZone(): BelongsTo
    {
        return $this->belongsTo(EventZone::class, 'to_zone_id');
    }
}
