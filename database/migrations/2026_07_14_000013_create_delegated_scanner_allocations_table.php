<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delegated_scanner_allocations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organizer_tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->char('delegation_public_id', 26);
            $table->char('venue_asset_public_id', 26);
            $table->char('scanner_public_id', 26);
            $table->json('granted_capabilities');
            $table->timestamp('starts_at', 6);
            $table->timestamp('ends_at', 6);
            $table->timestamp('released_at', 6)->nullable();
            $table->char('idempotency_key_hash', 64);
            $table->timestamps(6);

            $table->unique(
                ['tenant_id', 'delegation_public_id', 'idempotency_key_hash'],
                'scanner_allocations_idempotency_unique',
            );
            $table->index(
                ['tenant_id', 'delegation_public_id', 'released_at'],
                'scanner_allocations_delegation_index',
            );
            $table->index(
                ['organizer_tenant_id', 'event_id'],
                'scanner_allocations_organizer_event_index',
            );
        });

        DB::statement('ALTER TABLE delegated_scanner_allocations ADD CONSTRAINT scanner_allocations_window_chk CHECK (ends_at > starts_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('delegated_scanner_allocations');
    }
};
