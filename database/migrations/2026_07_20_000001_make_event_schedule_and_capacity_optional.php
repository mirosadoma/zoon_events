<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Schedule lives on EventVenue; capacity lives on category venue days.
 * Keep denormalized event columns nullable for backwards compatibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE events DROP CHECK events_schedule_chk');
        DB::statement('ALTER TABLE events DROP CHECK events_capacity_chk');

        DB::statement('ALTER TABLE events MODIFY start_at TIMESTAMP(6) NULL');
        DB::statement('ALTER TABLE events MODIFY end_at TIMESTAMP(6) NULL');
        DB::statement('ALTER TABLE events MODIFY registration_opens_at TIMESTAMP(6) NULL');
        DB::statement('ALTER TABLE events MODIFY registration_closes_at TIMESTAMP(6) NULL');

        DB::statement(
            'ALTER TABLE events ADD CONSTRAINT events_schedule_chk CHECK (
                (start_at IS NULL AND end_at IS NULL AND registration_opens_at IS NULL AND registration_closes_at IS NULL)
                OR (
                    start_at IS NOT NULL AND end_at IS NOT NULL
                    AND registration_opens_at IS NOT NULL AND registration_closes_at IS NOT NULL
                    AND end_at > start_at
                    AND registration_closes_at >= registration_opens_at
                    AND registration_closes_at <= end_at
                )
            )'
        );
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_capacity_chk CHECK (capacity IS NULL OR capacity > 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE events DROP CHECK events_schedule_chk');
        DB::statement('ALTER TABLE events DROP CHECK events_capacity_chk');

        DB::statement('ALTER TABLE events MODIFY start_at TIMESTAMP(6) NOT NULL');
        DB::statement('ALTER TABLE events MODIFY end_at TIMESTAMP(6) NOT NULL');
        DB::statement('ALTER TABLE events MODIFY registration_opens_at TIMESTAMP(6) NOT NULL');
        DB::statement('ALTER TABLE events MODIFY registration_closes_at TIMESTAMP(6) NOT NULL');

        DB::statement('ALTER TABLE events ADD CONSTRAINT events_schedule_chk CHECK (end_at > start_at AND registration_closes_at >= registration_opens_at AND registration_closes_at <= end_at)');
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_capacity_chk CHECK (capacity IS NULL OR capacity > 0)');
    }
};
