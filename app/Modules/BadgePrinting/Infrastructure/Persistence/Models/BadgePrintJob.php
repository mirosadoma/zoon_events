<?php

namespace App\Modules\BadgePrinting\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BadgePrintJob extends Model
{
    use HasUlids;

    protected $fillable = [
        'id', 'tenant_id', 'event_id', 'attendee_id', 'credential_id',
        'badge_template_id', 'kiosk_id', 'printed_by_user_id',
        'status', 'failure_reason', 'is_reprint', 'reprint_reason',
        'original_print_job_id', 'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_reprint' => 'boolean',
            'printed_at' => 'datetime',
        ];
    }

    public function badgeTemplate(): BelongsTo
    {
        return $this->belongsTo(BadgeTemplate::class);
    }

    public function originalPrintJob(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_print_job_id');
    }
}
