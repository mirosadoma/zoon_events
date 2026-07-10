<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_lanes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->string('name', 120);
            $table->string('external_acs_lane_id', 160);
            $table->string('gate_type', 20);
            $table->string('access_direction', 20);
            $table->boolean('is_admission_lane')->default(false);
            $table->string('status', 20)->default('active');
            $table->string('health_status', 20)->default('offline');
            $table->timestamp('last_seen_at', 6)->nullable();
            $table->timestamps(6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'zone_id'])->references(['tenant_id', 'event_id', 'id'])->on('acs_zones')->restrictOnDelete();
            $table->unique(['tenant_id', 'event_id', 'id'], 'acs_lanes_scope_unique');
            $table->unique(['tenant_id', 'event_id', 'external_acs_lane_id'], 'acs_lanes_external_uq');
            $table->index(['tenant_id', 'event_id', 'zone_id', 'status'], 'acs_lanes_zone_status_index');
        });

        DB::statement("ALTER TABLE acs_lanes ADD CONSTRAINT acs_lanes_gate_type_chk CHECK (gate_type IN ('turnstile','door','speedgate','manual'))");
        DB::statement("ALTER TABLE acs_lanes ADD CONSTRAINT acs_lanes_access_direction_chk CHECK (access_direction IN ('entry','exit','bidirectional'))");
        DB::statement("ALTER TABLE acs_lanes ADD CONSTRAINT acs_lanes_status_chk CHECK (status IN ('active','inactive'))");
        DB::statement("ALTER TABLE acs_lanes ADD CONSTRAINT acs_lanes_health_status_chk CHECK (health_status IN ('online','degraded','offline'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_lanes');
    }
};
