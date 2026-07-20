<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE events DROP CHECK events_schedule_chk');
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_schedule_chk CHECK (end_at > start_at AND registration_closes_at >= registration_opens_at AND registration_closes_at <= end_at)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE events DROP CHECK events_schedule_chk');
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_schedule_chk CHECK (end_at > start_at AND registration_closes_at > registration_opens_at AND registration_closes_at <= end_at)');
    }
};
