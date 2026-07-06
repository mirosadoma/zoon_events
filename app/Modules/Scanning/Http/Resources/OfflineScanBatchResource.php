<?php

namespace App\Modules\Scanning\Http\Resources;

use App\Modules\Scanning\Infrastructure\Persistence\Models\OfflineScanReconciliationBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OfflineScanReconciliationBatch */
final class OfflineScanBatchResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'batch_id' => $this->id,
            'status' => $this->status,
            'submitted_scan_count' => $this->submitted_scan_count,
            'accepted_count' => $this->accepted_count,
            'duplicate_count' => $this->duplicate_count,
            'conflict_count' => $this->conflict_count,
        ];
    }
}
