<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_authorization_rules', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('event_id', 26);
            $table->char('ticket_type_id', 26)->nullable();
            $table->string('attendee_type', 20)->nullable();
            $table->char('zone_id', 26);
            $table->char('lane_id', 26)->nullable();
            $table->string('access_direction', 20);
            $table->boolean('anti_passback_exempt')->default(false);
            $table->timestamp('valid_from', 6)->nullable();
            $table->timestamp('valid_until', 6)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps(6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'], 'acs_rules_event_fk')
                ->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'ticket_type_id'], 'acs_rules_ticket_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('ticket_types')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'zone_id'], 'acs_rules_zone_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('acs_zones')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'lane_id'], 'acs_rules_lane_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('acs_lanes')->restrictOnDelete();
            $table->index(['tenant_id', 'event_id', 'zone_id', 'status'], 'acs_authorization_rules_zone_status_index');
        });

        DB::statement("ALTER TABLE acs_authorization_rules ADD CONSTRAINT acs_authorization_rules_direction_chk CHECK (access_direction IN ('entry','exit','bidirectional'))");
        DB::statement("ALTER TABLE acs_authorization_rules ADD CONSTRAINT acs_authorization_rules_status_chk CHECK (status IN ('active','inactive'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_authorization_rules');
    }
};
