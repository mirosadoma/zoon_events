<?php

namespace App\Modules\Scanning\Infrastructure\Persistence;

use App\Modules\Scanning\Contracts\ScanEventPersonalDataAnonymizer;
use Illuminate\Support\Facades\DB;

final class DatabaseScanEventPersonalDataAnonymizer implements ScanEventPersonalDataAnonymizer
{
    public function anonymizeForAttendee(string $tenantId, string $attendeeId): void
    {
        DB::table('scan_events')
            ->where('tenant_id', $tenantId)
            ->where('attendee_id', $attendeeId)
            ->update([
                'attendee_id' => null,
                'attendee_display_name_ciphertext' => 'anonymized',
            ]);
    }
}
