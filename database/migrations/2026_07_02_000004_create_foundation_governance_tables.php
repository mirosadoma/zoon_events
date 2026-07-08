<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 16);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('actor_type', 32);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 160);
            $table->string('target_type', 120)->nullable();
            $table->string('target_id', 64)->nullable();
            $table->string('outcome', 16);
            $table->string('reason_code', 120)->nullable();
            $table->string('channel', 32);
            $table->string('correlation_id', 64);
            $table->string('request_id', 64)->nullable();
            $table->string('source_fingerprint', 128)->nullable();
            $table->string('client_fingerprint', 128)->nullable();
            $table->json('change_summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at', 6);
            $table->string('integrity_algorithm', 32);
            $table->string('integrity_key_id', 64);
            $table->string('integrity_hash', 64);
            $table->timestamp('created_at', 6)->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->index(['tenant_id', 'occurred_at', 'id']);
            $table->index(['tenant_id', 'actor_id', 'occurred_at', 'id'], 'audit_logs_tenant_actor_time_idx');
            $table->index(['tenant_id', 'action', 'occurred_at', 'id'], 'audit_logs_tenant_action_time_idx');
            $table->index(['scope', 'occurred_at', 'id']);
        });

        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_scope_chk CHECK (
            (scope = 'tenant' AND tenant_id IS NOT NULL)
            OR (scope = 'platform' AND tenant_id IS NULL)
        )");
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_outcome_chk CHECK (outcome IN ('succeeded', 'denied', 'failed'))");

        Schema::create('tenant_configurations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('key', 80);
            $table->unsignedInteger('schema_version');
            $table->json('value');
            $table->string('status', 16)->default('draft');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('activated_by_user_id')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('activated_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'key']);
            $table->index(['tenant_id', 'status', 'key']);
        });

        Schema::create('feature_flags', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('name', 160);
            $table->string('description', 500);
            $table->string('owner', 120);
            $table->string('value_type', 16);
            $table->json('default_value');
            $table->string('status', 16)->default('draft');
            $table->string('security_class', 24)->default('optional_capability');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('feature_flag_overrides', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('feature_flag_id')->nullable();
            $table->json('value');
            $table->string('status', 16)->default('active');
            $table->string('reason', 500);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('feature_flag_id')->references('id')->on('feature_flags')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['tenant_id', 'feature_flag_id']);
            $table->index(['tenant_id', 'status', 'expires_at']);
        });

        DB::statement("ALTER TABLE tenant_configurations ADD CONSTRAINT tenant_configurations_status_chk CHECK (status IN ('draft', 'active'))");
        DB::statement("ALTER TABLE feature_flags ADD CONSTRAINT feature_flags_type_chk CHECK (value_type IN ('boolean', 'integer', 'string'))");
        DB::statement("ALTER TABLE feature_flags ADD CONSTRAINT feature_flags_status_chk CHECK (status IN ('draft', 'active', 'disabled', 'retired'))");
        DB::statement("ALTER TABLE feature_flag_overrides ADD CONSTRAINT feature_flag_overrides_status_chk CHECK (status IN ('active', 'disabled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flag_overrides');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('tenant_configurations');
        Schema::dropIfExists('audit_logs');
    }
};
