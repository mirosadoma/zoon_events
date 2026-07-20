<?php

namespace App\Modules\Notifications\Infrastructure\Persistence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InAppNotification extends Model
{
    protected $fillable = [
        'user_id',
        'tenant_id',
        'type',
        'action',
        'target_type',
        'target_id',
        'actor_name',
        'link',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
