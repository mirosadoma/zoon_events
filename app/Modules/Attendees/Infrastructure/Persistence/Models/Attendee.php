<?php

namespace App\Modules\Attendees\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Attendee extends Model
{
    protected $fillable = [
        'tenant_id', 'event_id', 'order_id', 'order_item_id', 'ticket_type_id',
        'submission_id', 'first_name_ciphertext', 'last_name_ciphertext',
        'email_ciphertext', 'phone_ciphertext', 'email_index', 'phone_index',
        'encryption_key_id', 'preferred_locale', 'registered_at',
        'checkin_status', 'first_checked_in_at', 'last_scan_event_id', 'origin',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'immutable_datetime',
            'first_checked_in_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'anonymized_at' => 'immutable_datetime',
            'legal_hold_at' => 'immutable_datetime',
        ];
    }

    public function lastScanEvent(): BelongsTo
    {
        return $this->belongsTo('App\\Modules\\Scanning\\Infrastructure\\Persistence\\Models\\ScanEvent', 'last_scan_event_id');
    }
}
