<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_availability_windows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('venue_asset_id');
            $table->char('public_id', 26)->unique();
            $table->timestamp('available_from', 6);
            $table->timestamp('available_until', 6);
            $table->dateTime('local_from', 6);
            $table->dateTime('local_until', 6);
            $table->string('source_timezone', 64);
            $table->string('status', 20)->default('available');
            $table->string('reason_code', 80)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by_user_id');
            $table->unsignedBigInteger('updated_by_user_id');
            $table->timestamps(6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'venue_asset_id'])->references(['tenant_id', 'id'])->on('venue_assets')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['tenant_id', 'id'], 'availability_scope_unique');
            $table->index(
                ['tenant_id', 'venue_asset_id', 'status', 'available_from', 'available_until'],
                'availability_lookup_index',
            );
        });

        DB::statement("ALTER TABLE asset_availability_windows ADD CONSTRAINT availability_status_chk CHECK (status IN ('available','blocked','retired'))");
        DB::statement('ALTER TABLE asset_availability_windows ADD CONSTRAINT availability_interval_chk CHECK (available_until > available_from AND local_until > local_from)');
        DB::statement('ALTER TABLE asset_availability_windows ADD CONSTRAINT availability_version_chk CHECK (version >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_availability_windows');
    }
};
