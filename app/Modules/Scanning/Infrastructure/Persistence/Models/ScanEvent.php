<?php

namespace App\Modules\Scanning\Infrastructure\Persistence\Models;

use Database\Factories\ScanEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ScanEvent extends Model
{
    /** @use HasFactory<ScanEventFactory> */
    use HasFactory;

    use HasUlids;

    public const UPDATED_AT = null;

    protected static function newFactory(): ScanEventFactory
    {
        return ScanEventFactory::new();
    }

    protected $fillable = [
        'id',
        'tenant_id',
        'event_id',
        'attendee_id',
        'credential_id',
        'scanner_type',
        'scanner_id',
        'gate_id',
        'zone_id',
        'direction',
        'result',
        'reason',
        'attendee_display_name_ciphertext',
        'offline_mode',
        'scanned_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'offline_mode' => 'boolean',
            'scanned_at' => 'immutable_datetime',
            'synced_at' => 'immutable_datetime',
        ];
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo('App\\Modules\\Credentials\\Infrastructure\\Persistence\\Models\\Credential');
    }
}
