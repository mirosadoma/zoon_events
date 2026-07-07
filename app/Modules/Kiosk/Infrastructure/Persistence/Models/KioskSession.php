<?php

namespace App\Modules\Kiosk\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class KioskSession extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'id', 'tenant_id', 'kiosk_id', 'secret_hash',
        'confirmed_at', 'expires_at', 'revoked_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'expires_at'   => 'datetime',
            'revoked_at'   => 'datetime',
            'created_at'   => 'datetime',
        ];
    }

    public function kiosk(): BelongsTo
    {
        return $this->belongsTo(Kiosk::class);
    }
}
