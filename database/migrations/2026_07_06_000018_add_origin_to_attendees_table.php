<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendees', function (Blueprint $table): void {
            $table->string('origin', 20)->default('standard')->after('last_scan_event_id');
        });

        DB::statement("ALTER TABLE attendees ADD CONSTRAINT attendees_origin_chk CHECK (origin IN ('standard','walk_up'))");
    }

    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $table): void {
            $table->dropColumn('origin');
        });
    }
};
