<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCategoryPrivilege extends Model
{
    protected $fillable = [
        'event_category_id',
        'key',
        'label',
        'label_ar',
        'effect',
        'target_type',
        'target_id',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'event_category_id');
    }
}
