<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE events DROP CONSTRAINT events_tier_chk');
        DB::statement("ALTER TABLE events ADD CONSTRAINT events_tier_chk CHECK (tier IN ('public','private','both'))");
    }

    public function down(): void
    {
        DB::table('events')->where('tier', 'both')->update(['tier' => 'public']);
        DB::statement('ALTER TABLE events DROP CONSTRAINT events_tier_chk');
        DB::statement("ALTER TABLE events ADD CONSTRAINT events_tier_chk CHECK (tier IN ('public','private'))");
    }
};
