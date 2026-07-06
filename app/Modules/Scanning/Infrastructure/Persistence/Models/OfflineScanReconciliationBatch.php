<?php

namespace App\Modules\Scanning\Infrastructure\Persistence\Models;

use Database\Factories\OfflineScanReconciliationBatchFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class OfflineScanReconciliationBatch extends Model
{
    /** @use HasFactory<OfflineScanReconciliationBatchFactory> */
    use HasFactory;

    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'tenant_id',
        'event_id',
        'device_reference',
        'allowlist_issued_at',
        'allowlist_expires_at',
        'submitted_scan_count',
        'accepted_count',
        'duplicate_count',
        'conflict_count',
        'status',
        'processed_at',
    ];

    protected static function newFactory(): OfflineScanReconciliationBatchFactory
    {
        return OfflineScanReconciliationBatchFactory::new();
    }

    protected function casts(): array
    {
        return [
            'allowlist_issued_at' => 'immutable_datetime',
            'allowlist_expires_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
