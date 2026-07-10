<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_exports', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 16);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->json('filters');
            $table->string('status', 24)->default('pending');
            $table->string('storage_path', 500)->nullable();
            $table->unsignedBigInteger('record_count')->nullable();
            $table->string('failure_code', 120)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('requested_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['tenant_id', 'status', 'created_at', 'id']);
        });

        DB::statement("ALTER TABLE audit_exports ADD CONSTRAINT audit_exports_scope_chk CHECK (
            (scope = 'tenant' AND tenant_id IS NOT NULL)
            OR (scope = 'platform' AND tenant_id IS NULL)
        )");
        DB::statement("ALTER TABLE audit_exports ADD CONSTRAINT audit_exports_status_chk CHECK (status IN ('pending', 'processing', 'completed', 'failed', 'expired'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_exports');
    }
};
