<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_reservations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organizer_tenant_id');
            $table->unsignedBigInteger('rental_request_id');
            $table->unsignedBigInteger('rental_asset_id');
            $table->unsignedBigInteger('venue_asset_id');
            $table->timestamp('reserved_from', 6);
            $table->timestamp('reserved_until', 6);
            $table->string('status', 20)->default('reserved');
            $table->string('release_reason_code', 80)->nullable();
            $table->timestamp('activated_at', 6)->nullable();
            $table->timestamp('completed_at', 6)->nullable();
            $table->timestamp('released_at', 6)->nullable();
            $table->timestamps(6);

            $table->foreign(
                ['tenant_id', 'organizer_tenant_id', 'rental_request_id'],
                'asset_reservations_request_fk',
            )->references(['tenant_id', 'organizer_tenant_id', 'id'])
                ->on('rental_requests')->restrictOnDelete();
            $table->foreign('rental_asset_id')
                ->references('id')->on('rental_assets')->restrictOnDelete();
            $table->foreign(['tenant_id', 'venue_asset_id'])
                ->references(['tenant_id', 'id'])->on('venue_assets')->restrictOnDelete();
            $table->unique(['tenant_id', 'rental_asset_id'], 'asset_reservations_rental_asset_unique');
            $table->index(
                ['tenant_id', 'venue_asset_id', 'status', 'reserved_from', 'reserved_until'],
                'asset_reservations_conflict_index',
            );
            $table->index(
                ['tenant_id', 'organizer_tenant_id', 'rental_request_id', 'status'],
                'asset_reservations_request_index',
            );
        });

        DB::statement("ALTER TABLE asset_reservations ADD CONSTRAINT asset_reservations_status_chk CHECK (status IN ('reserved','active','completed','released'))");
        DB::statement('ALTER TABLE asset_reservations ADD CONSTRAINT asset_reservations_window_chk CHECK (reserved_until > reserved_from)');
        DB::statement("ALTER TABLE asset_reservations ADD CONSTRAINT asset_reservations_release_chk CHECK ((status = 'released' AND released_at IS NOT NULL AND release_reason_code IS NOT NULL) OR (status <> 'released' AND released_at IS NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_reservations');
    }
};
