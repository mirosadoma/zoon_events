<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_records', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 16);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('scope_identifier', 26);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('operation', 160);
            $table->char('key_hash', 64);
            $table->char('request_hash', 64);
            $table->string('state', 16)->default('processing');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['scope', 'scope_identifier', 'actor_id', 'operation', 'key_hash'], 'idempotency_scope_key_unique');
            $table->index(['expires_at', 'state']);
        });

        DB::statement("ALTER TABLE idempotency_records ADD CONSTRAINT idempotency_scope_chk CHECK (
            (scope = 'tenant' AND tenant_id IS NOT NULL AND scope_identifier = tenant_id)
            OR (scope = 'platform' AND tenant_id IS NULL AND scope_identifier = 'platform')
        )");
        DB::statement("ALTER TABLE idempotency_records ADD CONSTRAINT idempotency_state_chk CHECK (state IN ('processing', 'completed', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
    }
};
