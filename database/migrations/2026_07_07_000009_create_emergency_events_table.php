<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->string('signal_source', 20);
            $table->string('behavior_applied', 20);
            $table->timestamp('raised_at', 6);
            $table->timestamp('cleared_at', 6)->nullable();
            $table->timestamps(6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'zone_id'])->references(['tenant_id', 'event_id', 'id'])->on('acs_zones')->restrictOnDelete();
            $table->index(['tenant_id', 'event_id', 'cleared_at'], 'emergency_events_active_index');
        });

        DB::statement("ALTER TABLE emergency_events ADD CONSTRAINT emergency_events_source_chk CHECK (signal_source IN ('operator','acs','fire_alarm','system'))");
        DB::statement("ALTER TABLE emergency_events ADD CONSTRAINT emergency_events_behavior_chk CHECK (behavior_applied IN ('fail_open','fail_closed','mixed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_events');
    }
};
