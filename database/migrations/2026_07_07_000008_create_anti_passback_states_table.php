<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anti_passback_states', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('credential_id')->nullable();
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->string('state', 10)->default('outside');
            $table->unsignedBigInteger('last_access_event_id')->nullable();
            $table->timestamp('last_transition_at', 6)->nullable();
            $table->timestamps(6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'credential_id'])->references(['tenant_id', 'event_id', 'id'])->on('credentials')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'zone_id'])->references(['tenant_id', 'event_id', 'id'])->on('acs_zones')->restrictOnDelete();
            $table->unique(['tenant_id', 'event_id', 'credential_id', 'zone_id'], 'anti_passback_states_scope_uq');
        });

        DB::statement("ALTER TABLE anti_passback_states ADD CONSTRAINT anti_passback_states_state_chk CHECK (state IN ('inside','outside'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('anti_passback_states');
    }
};
