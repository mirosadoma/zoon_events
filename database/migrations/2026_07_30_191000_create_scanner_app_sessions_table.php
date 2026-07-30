<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scanner_app_sessions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('event_zone_id');
            $table->string('token_hash', 64);
            $table->timestamp('expires_at', 6);
            $table->timestamp('revoked_at', 6)->nullable();
            $table->timestamp('last_seen_at', 6)->nullable();
            $table->timestamps(6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign('event_zone_id')->references('id')->on('event_zones')->restrictOnDelete();
            $table->unique(['token_hash'], 'scanner_app_sessions_token_uq');
            $table->index(['tenant_id', 'event_id', 'event_zone_id'], 'scanner_app_sessions_zone_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanner_app_sessions');
    }
};
