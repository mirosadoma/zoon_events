<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acs_zones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->string('name', 120);
            $table->string('external_acs_zone_id', 160);
            $table->boolean('anti_passback_enabled')->default(false);
            $table->string('unavailability_mode', 20)->default('fail_closed');
            $table->string('emergency_egress_mode', 20)->default('fail_open');
            $table->string('status', 20)->default('active');
            $table->timestamps(6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->unique(['tenant_id', 'event_id', 'id'], 'acs_zones_scope_unique');
            $table->unique(['tenant_id', 'event_id', 'external_acs_zone_id'], 'acs_zones_external_uq');
            $table->index(['tenant_id', 'event_id', 'status'], 'acs_zones_event_status_index');
        });

        DB::statement("ALTER TABLE acs_zones ADD CONSTRAINT acs_zones_unavailability_chk CHECK (unavailability_mode IN ('fail_open','fail_closed'))");
        DB::statement("ALTER TABLE acs_zones ADD CONSTRAINT acs_zones_emergency_chk CHECK (emergency_egress_mode IN ('fail_open','fail_closed'))");
        DB::statement("ALTER TABLE acs_zones ADD CONSTRAINT acs_zones_status_chk CHECK (status IN ('active','inactive'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('acs_zones');
    }
};
