<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE scan_events DROP CONSTRAINT scan_events_scanner_type_chk');

        $scannerTypes = implode(',', [
            "'staff_phone'",
            "'handheld_scanner'",
            "'kiosk'",
            "'gate'",
            "'acs_lane'",
            "'acs_gate'",
            "'manual_desk'",
        ]);

        DB::statement("ALTER TABLE scan_events ADD CONSTRAINT scan_events_scanner_type_chk CHECK (scanner_type IN ({$scannerTypes}))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE scan_events DROP CONSTRAINT scan_events_scanner_type_chk');

        $scannerTypes = implode(',', [
            "'staff_phone'",
            "'handheld_scanner'",
            "'kiosk'",
            "'gate'",
            "'acs_lane'",
            "'manual_desk'",
        ]);

        DB::statement("ALTER TABLE scan_events ADD CONSTRAINT scan_events_scanner_type_chk CHECK (scanner_type IN ({$scannerTypes}))");
    }
};
