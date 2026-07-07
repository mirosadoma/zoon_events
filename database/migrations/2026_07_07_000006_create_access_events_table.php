<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_events', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('event_id', 26);
            $table->string('event_type', 20);
            $table->char('credential_id', 26)->nullable();
            $table->char('zone_id', 26)->nullable();
            $table->char('lane_id', 26)->nullable();
            $table->string('direction', 10)->default('none');
            $table->string('decision', 10)->default('n/a');
            $table->string('reason_code', 40);
            $table->string('source', 20)->default('acs_gate');
            $table->string('external_event_id', 160)->nullable();
            $table->char('scan_event_id', 26)->nullable();
            $table->timestamp('occurred_at', 6);
            $table->timestamp('created_at', 6)->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'], 'access_events_event_fk')
                ->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'credential_id'], 'access_events_credential_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('credentials')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'zone_id'], 'access_events_zone_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('acs_zones')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'lane_id'], 'access_events_lane_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('acs_lanes')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'scan_event_id'], 'access_events_scan_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('scan_events')->restrictOnDelete();
            $table->unique(['tenant_id', 'external_event_id'], 'access_events_external_uq');
            $table->index(['tenant_id', 'event_id', 'occurred_at'], 'access_events_timeline_index');
            $table->index(['tenant_id', 'event_id', 'credential_id', 'zone_id', 'occurred_at'], 'access_events_credential_zone_timeline_index');
        });

        DB::statement("ALTER TABLE access_events ADD CONSTRAINT access_events_type_chk CHECK (event_type IN ('decision','entry','exit','emergency'))");
        DB::statement("ALTER TABLE access_events ADD CONSTRAINT access_events_decision_chk CHECK (decision IN ('allow','deny','n/a'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('access_events');
    }
};
