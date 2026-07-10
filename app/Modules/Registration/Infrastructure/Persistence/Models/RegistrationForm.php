<?php

namespace App\Modules\Registration\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class RegistrationForm extends Model
{
    protected $fillable = ['tenant_id', 'event_id', 'name', 'status', 'created_by_user_id'];

    public function versions(): HasMany
    {
        return $this->hasMany(RegistrationFormVersion::class);
    }
}
