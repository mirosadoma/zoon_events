<?php

namespace Database\Factories;

use App\Modules\Scanning\Infrastructure\Persistence\Models\OfflineScanReconciliationBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OfflineScanReconciliationBatch> */
final class OfflineScanReconciliationBatchFactory extends Factory
{
    protected $model = OfflineScanReconciliationBatch::class;

    public function definition(): array
    {
        return [
            'device_reference' => 'device-'.fake()->unique()->lexify('????'),
            'allowlist_issued_at' => now(),
            'allowlist_expires_at' => now()->addHours(4),
            'submitted_scan_count' => 0,
            'accepted_count' => 0,
            'duplicate_count' => 0,
            'conflict_count' => 0,
            'status' => 'received',
            'processed_at' => null,
        ];
    }
}
