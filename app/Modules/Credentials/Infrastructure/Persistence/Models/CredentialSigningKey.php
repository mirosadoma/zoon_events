<?php

namespace App\Modules\Credentials\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class CredentialSigningKey extends Model
{
    protected $primaryKey = 'key_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key_id', 'public_key', 'private_key_reference', 'status', 'not_before', 'verify_until'];
}
