<?php

namespace App\Modules\Attendees\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class Attendee extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id', 'event_id', 'order_id', 'order_item_id', 'ticket_type_id',
        'submission_id', 'first_name_ciphertext', 'last_name_ciphertext',
        'email_ciphertext', 'phone_ciphertext', 'email_index', 'phone_index',
        'encryption_key_id', 'preferred_locale', 'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'anonymized_at' => 'immutable_datetime',
            'legal_hold_at' => 'immutable_datetime',
        ];
    }
}
