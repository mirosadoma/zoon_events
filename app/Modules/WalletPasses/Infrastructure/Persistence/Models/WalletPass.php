<?php

namespace App\Modules\WalletPasses\Infrastructure\Persistence\Models;

use App\Modules\WalletPasses\Domain\WalletPassStatus;
use Database\Factories\WalletPassFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WalletPass extends Model
{
    /** @use HasFactory<WalletPassFactory> */
    use HasFactory;

    protected static function newFactory(): WalletPassFactory
    {
        return WalletPassFactory::new();
    }

    protected $fillable = [
        'id',
        'tenant_id',
        'event_id',
        'attendee_id',
        'credential_id',
        'provider',
        'pass_serial_number',
        'pass_url',
        'status',
        'last_pushed_at',
        'last_push_reason_code',
        'superseded_by_id',
        'apple_authentication_token',
        'pass_content_updated_at',
    ];

    protected $hidden = [
        'apple_authentication_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => WalletPassStatus::class,
            'last_pushed_at' => 'immutable_datetime',
            'pass_content_updated_at' => 'immutable_datetime',
        ];
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo('App\\Modules\\Attendees\\Infrastructure\\Persistence\\Models\\Attendee');
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo('App\\Modules\\Credentials\\Infrastructure\\Persistence\\Models\\Credential');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }
}
