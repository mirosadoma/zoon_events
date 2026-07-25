<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventAgendaItem extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'event_venue_id',
        'agenda_date',
        'title_en',
        'title_ar',
        'description_en',
        'description_ar',
        'start_at',
        'end_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'agenda_date' => 'immutable_date',
            'start_at' => 'immutable_datetime',
            'end_at' => 'immutable_datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue::class, 'event_venue_id');
    }
}
