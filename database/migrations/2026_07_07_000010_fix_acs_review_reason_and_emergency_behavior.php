<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE access_events MODIFY reason_code VARCHAR(40) NULL');
        DB::statement('ALTER TABLE emergency_events DROP CHECK emergency_events_behavior_chk');
        DB::statement("ALTER TABLE emergency_events ADD CONSTRAINT emergency_events_behavior_chk CHECK (behavior_applied IN ('fail_open','fail_closed','mixed'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE access_events SET reason_code = 'allowed' WHERE reason_code IS NULL AND decision = 'allow'");
        DB::statement("UPDATE access_events SET reason_code = 'credential_unknown' WHERE reason_code IS NULL AND decision = 'deny'");
        DB::statement("UPDATE access_events SET reason_code = event_type WHERE reason_code IS NULL AND decision = 'n/a'");
        DB::statement('ALTER TABLE access_events MODIFY reason_code VARCHAR(40) NOT NULL');
        DB::statement('ALTER TABLE emergency_events DROP CHECK emergency_events_behavior_chk');
        DB::statement("UPDATE emergency_events SET behavior_applied = 'fail_open' WHERE behavior_applied = 'mixed'");
        DB::statement("ALTER TABLE emergency_events ADD CONSTRAINT emergency_events_behavior_chk CHECK (behavior_applied IN ('fail_open','fail_closed'))");
    }
};
