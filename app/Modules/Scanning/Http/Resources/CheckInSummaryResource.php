<?php

namespace App\Modules\Scanning\Http\Resources;

use App\Modules\Scanning\Infrastructure\Persistence\Models\EventCheckInSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EventCheckInSummary */
final class CheckInSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'registered_count' => $this->registered_count,
            'checked_in_count' => $this->checked_in_count,
            'rejected_count' => $this->rejected_count,
            'duplicate_count' => $this->duplicate_count,
            'last_scan_at' => $this->last_scan_at?->toIso8601String(),
        ];
    }
}
