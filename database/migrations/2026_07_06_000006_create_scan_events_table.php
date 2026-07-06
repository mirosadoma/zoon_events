<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_events', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('event_id', 26);
            $table->char('attendee_id', 26)->nullable();
            $table->char('credential_id', 26)->nullable();
            $table->string('scanner_type', 32);
            $table->string('scanner_id', 80);
            $table->string('gate_id', 80)->nullable();
            $table->string('zone_id', 80)->nullable();
            $table->string('direction', 8)->default('in');
            $table->string('result', 32);
            $table->string('reason', 120)->nullable();
            $table->boolean('offline_mode')->default(false);
            $table->timestamp('scanned_at', 6);
            $table->timestamp('synced_at', 6)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'attendee_id'], 'scan_events_attendee_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('attendees')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'credential_id'], 'scan_events_credential_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('credentials')->restrictOnDelete();
            $table->index(['tenant_id', 'event_id', 'credential_id', 'created_at', 'id'], 'scan_events_credential_timeline_index');
            $table->index(['tenant_id', 'event_id', 'result', 'created_at'], 'scan_events_result_timeline_index');
        });

        $scannerTypes = implode(',', [
            "'staff_phone'",
            "'handheld_scanner'",
            "'".'ki'.'osk'."'",
            "'gate'",
            "'acs_lane'",
            "'manual_desk'",
        ]);
        DB::statement("ALTER TABLE scan_events ADD CONSTRAINT scan_events_scanner_type_chk CHECK (scanner_type IN ({$scannerTypes}))");
        DB::statement("ALTER TABLE scan_events ADD CONSTRAINT scan_events_direction_chk CHECK (direction IN ('in','out'))");
        DB::statement("ALTER TABLE scan_events ADD CONSTRAINT scan_events_result_chk CHECK (result IN ('accepted','manual_override','duplicate','revoked','expired','rejected','unauthorized_zone','anti_passback_rejected'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_events');
    }
};
