<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_pass_apple_device_registrations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('wallet_pass_id')->nullable();
            $table->string('device_library_identifier', 120);
            $table->string('push_token', 120);
            $table->timestamp('registered_at', 6);
            $table->timestamp('unregistered_at', 6)->nullable();

            $table->unique(
                ['wallet_pass_id', 'device_library_identifier'],
                'wallet_pass_apple_regs_pass_device_unique',
            );
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('wallet_pass_id')->references('id')->on('wallet_passes')->restrictOnDelete();
            $table->index(
                ['tenant_id', 'wallet_pass_id', 'unregistered_at'],
                'wallet_pass_apple_device_registrations_active_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_pass_apple_device_registrations');
    }
};
