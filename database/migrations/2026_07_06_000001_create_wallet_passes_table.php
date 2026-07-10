<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_passes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('attendee_id')->nullable();
            $table->unsignedBigInteger('credential_id')->nullable();
            $table->string('provider', 16);
            $table->string('pass_serial_number', 80);
            $table->string('pass_url', 2048)->nullable();
            $table->string('status', 24)->default('created');
            $table->timestamp('last_pushed_at', 6)->nullable();
            $table->string('last_push_reason_code', 120)->nullable();
            $table->unsignedBigInteger('superseded_by_id')->nullable();
            $table->timestamps(6);

            $table->unique(['tenant_id', 'event_id', 'id'], 'wallet_passes_scope_unique');
            $table->unique(['tenant_id', 'provider', 'pass_serial_number'], 'wallet_passes_tenant_id_provider_pass_serial_number_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'attendee_id'], 'wallet_passes_attendee_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('attendees')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'credential_id'], 'wallet_passes_credential_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('credentials')->restrictOnDelete();
            $table->index(['tenant_id', 'event_id', 'attendee_id', 'provider'], 'wallet_passes_attendee_provider_index');
            $table->index(['tenant_id', 'credential_id'], 'wallet_passes_credential_index');
        });

        Schema::table('wallet_passes', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'event_id', 'superseded_by_id'], 'wallet_passes_superseded_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('wallet_passes')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE wallet_passes ADD CONSTRAINT wallet_passes_provider_chk CHECK (provider IN ('apple','google'))");
        DB::statement("ALTER TABLE wallet_passes ADD CONSTRAINT wallet_passes_status_chk CHECK (status IN ('created','active','updated','revoked','expired','failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_passes');
    }
};
