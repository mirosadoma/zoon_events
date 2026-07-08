<?php

namespace App\Modules\Attendees\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class AttendeeCorrection extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'event_id', 'attendee_id', 'corrected_by_user_id',
        'changed_fields', 'reason', 'created_at',
    ];

    protected function casts(): array
    {
        return ['changed_fields' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
