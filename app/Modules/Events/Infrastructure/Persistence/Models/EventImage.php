<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventImage extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'path',
        'sort_order',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
