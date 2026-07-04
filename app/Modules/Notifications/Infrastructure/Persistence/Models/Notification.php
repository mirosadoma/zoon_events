<?php

namespace App\Modules\Notifications\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class Notification extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id', 'event_id', 'attendee_id', 'order_id', 'credential_id',
        'channel', 'template_key', 'template_version', 'locale',
        'destination_ciphertext', 'destination_index', 'encryption_key_id',
        'content_digest', 'adapter_key', 'provider_message_id', 'status',
        'attempt_count', 'next_attempt_at', 'last_reason_code',
    ];

    protected function casts(): array
    {
        return ['next_attempt_at' => 'immutable_datetime', 'attempt_count' => 'integer'];
    }
}
