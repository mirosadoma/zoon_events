<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventEmailTemplate extends Model
{
    protected $fillable = [
        'event_id',
        'tenant_id',
        'type',
        'subject_en',
        'subject_ar',
        'html_body_en',
        'html_body_ar',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
