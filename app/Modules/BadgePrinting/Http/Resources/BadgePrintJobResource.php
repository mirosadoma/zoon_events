<?php

namespace App\Modules\BadgePrinting\Http\Resources;

use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgePrintJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BadgePrintJob */
final class BadgePrintJobResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var BadgePrintJob $job */
        $job = $this->resource;

        return [
            'id'                   => $job->id,
            'status'               => $job->status,
            'failure_reason'       => $job->failure_reason,
            'is_reprint'           => $job->is_reprint,
            'reprint_reason'       => $job->reprint_reason,
            'original_print_job_id' => $job->original_print_job_id,
            'printed_at'           => $job->printed_at?->toIso8601String(),
        ];
    }
}
