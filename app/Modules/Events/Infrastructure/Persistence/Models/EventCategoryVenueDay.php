<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCategoryVenueDay extends Model
{
    protected $fillable = [
        'event_category_venue_id',
        'date',
        'capacity',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'capacity' => 'integer',
        ];
    }

    public function categoryVenue(): BelongsTo
    {
        return $this->belongsTo(EventCategoryVenue::class, 'event_category_venue_id');
    }
}
