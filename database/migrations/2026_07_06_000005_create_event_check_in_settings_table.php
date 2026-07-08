<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_check_in_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->boolean('single_entry_enabled')->default(true);
            $table->string('single_entry_scope', 24)->default('event');
            $table->timestamps(6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE event_check_in_settings ADD CONSTRAINT event_check_in_settings_scope_chk CHECK (single_entry_scope IN ('event','ticket_type'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('event_check_in_settings');
    }
};
