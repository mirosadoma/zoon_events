<?php

namespace App\Modules\AdminConsole\Infrastructure\Persistence\Models;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrganizerRegistrationRequest extends Model
{
    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'organization_name',
        'phone',
        'message',
        'status',
        'rejection_reason',
        'reviewed_by_user_id',
        'reviewed_at',
        'created_user_id',
        'created_tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function createdUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }

    public function createdTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'created_tenant_id');
    }
}
