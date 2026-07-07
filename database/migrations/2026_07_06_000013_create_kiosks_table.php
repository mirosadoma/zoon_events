<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosks', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('event_id', 26);
            $table->string('device_name', 120);
            $table->string('device_code', 40);
            $table->string('location_label', 160)->nullable();
            $table->string('status', 20)->default('registered');
            $table->string('printer_status', 20)->default('unknown');
            $table->timestamp('last_heartbeat_at', 6)->nullable();
            $table->boolean('confirmation_required')->default(false);
            $table->string('confirmation_code_hash', 255)->nullable();
            $table->timestamp('retired_at', 6)->nullable();
            $table->timestamps(6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->unique(['tenant_id', 'event_id', 'device_code']);
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'event_id', 'status']);
        });

        DB::statement("ALTER TABLE kiosks ADD CONSTRAINT kiosks_status_chk CHECK (status IN ('registered','online','offline','degraded','retired'))");
        DB::statement("ALTER TABLE kiosks ADD CONSTRAINT kiosks_printer_status_chk CHECK (printer_status IN ('unknown','ready','error','disconnected'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosks');
    }
};
