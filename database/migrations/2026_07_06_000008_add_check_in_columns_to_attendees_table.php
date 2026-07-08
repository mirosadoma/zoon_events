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
            $table->string('checkin_status', 24)->default('not_checked_in')->after('preferred_locale');
            $table->timestamp('first_checked_in_at', 6)->nullable()->after('checkin_status');
            $table->unsignedBigInteger('last_scan_event_id')->nullable()->after('first_checked_in_at');
        });

        Schema::table('attendees', function (Blueprint $table): void {
            $table->foreign('last_scan_event_id')->references('id')->on('scan_events')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE attendees ADD CONSTRAINT attendees_checkin_status_chk CHECK (checkin_status IN ('not_checked_in','checked_in'))");
    }

    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $table): void {
            $table->dropForeign(['last_scan_event_id']);
            $table->dropColumn(['checkin_status', 'first_checked_in_at', 'last_scan_event_id']);
        });
    }
};
