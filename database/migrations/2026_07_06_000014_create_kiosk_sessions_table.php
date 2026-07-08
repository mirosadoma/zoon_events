<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_sessions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('kiosk_id')->nullable();
            $table->string('secret_hash', 255);
            $table->timestamp('confirmed_at', 6)->nullable();
            $table->timestamp('expires_at', 6);
            $table->timestamp('revoked_at', 6)->nullable();
            $table->timestamp('created_at', 6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'kiosk_id'])->references(['tenant_id', 'id'])->on('kiosks')->restrictOnDelete();
            $table->index(['tenant_id', 'kiosk_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_sessions');
    }
};
