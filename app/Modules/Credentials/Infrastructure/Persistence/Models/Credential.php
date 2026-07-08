<?php

namespace App\Modules\Credentials\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class Credential extends Model
{
    protected $fillable = [
        'id', 'tenant_id', 'event_id', 'attendee_id', 'ticket_type_id', 'status',
        'token_version', 'key_id', 'nonce_hash', 'token_digest', 'presentation_token_ciphertext', 'issued_at',
        'expires_at', 'revoked_at', 'revoked_by_user_id', 'revocation_reason',
        'superseded_by_id',
    ];

    protected function casts(): array
    {
        return ['issued_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
