<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->string('name', 160);
            $table->string('slug', 100)->unique();
            $table->string('status', 24)->default('active');
            $table->string('default_locale', 10)->default('en');
            $table->string('timezone', 64);
            $table->string('data_residency_region', 64);
            $table->json('policy_profile')->nullable();
            $table->char('created_by_user_id', 26);
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['status', 'created_at', 'id']);
        });

        Schema::create('tenant_memberships', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('user_id', 26);
            $table->string('status', 24)->default('active');
            $table->char('created_by_user_id', 26);
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['tenant_id', 'user_id']);
            $table->unique(['tenant_id', 'id'], 'tenant_memberships_tenant_id_id_unique');
            $table->index(['tenant_id', 'status', 'created_at', 'id']);
            $table->index(['user_id', 'status']);
        });

        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_lifecycle_chk CHECK (
            (status = 'active' AND suspended_at IS NULL AND deactivated_at IS NULL)
            OR (status = 'suspended' AND suspended_at IS NOT NULL AND deactivated_at IS NULL)
            OR (status = 'deactivated' AND deactivated_at IS NOT NULL)
        )");

        DB::statement("ALTER TABLE tenant_memberships ADD CONSTRAINT tenant_memberships_lifecycle_chk CHECK (
            (status = 'active' AND suspended_at IS NULL AND deactivated_at IS NULL)
            OR (status = 'suspended' AND suspended_at IS NOT NULL AND deactivated_at IS NULL)
            OR (status = 'deactivated' AND deactivated_at IS NOT NULL)
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_memberships');
        Schema::dropIfExists('tenants');
    }
};
