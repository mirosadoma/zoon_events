<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registration_invites', function (Blueprint $table): void {
            $table->string('invite_status', 24)->default('not_registered')->after('is_active');
            $table->index(['event_id', 'invite_status'], 'eri_event_invite_status_index');
        });

        Schema::table('attendees', function (Blueprint $table): void {
            $table->string('invite_status', 24)->default('registered')->after('registration_status');
            $table->index(['tenant_id', 'event_id', 'invite_status'], 'attendees_invite_status_index');
        });

        DB::statement("ALTER TABLE event_registration_invites ADD CONSTRAINT eri_invite_status_chk CHECK (invite_status IN ('not_registered','registered','attended','not_attended'))");
        DB::statement("ALTER TABLE attendees ADD CONSTRAINT attendees_invite_status_chk CHECK (invite_status IN ('not_registered','registered','attended','not_attended'))");

        // Backfill: used invites → registered; checked-in attendees → attended.
        DB::table('event_registration_invites')
            ->where(function ($query): void {
                $query->whereNotNull('used_at')->orWhere('is_active', false);
            })
            ->update(['invite_status' => 'registered']);

        DB::table('attendees')
            ->where('checkin_status', 'checked_in')
            ->update(['invite_status' => 'attended']);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE attendees DROP CHECK attendees_invite_status_chk');
        DB::statement('ALTER TABLE event_registration_invites DROP CHECK eri_invite_status_chk');

        Schema::table('attendees', function (Blueprint $table): void {
            $table->dropIndex('attendees_invite_status_index');
            $table->dropColumn('invite_status');
        });

        Schema::table('event_registration_invites', function (Blueprint $table): void {
            $table->dropIndex('eri_event_invite_status_index');
            $table->dropColumn('invite_status');
        });
    }
};
