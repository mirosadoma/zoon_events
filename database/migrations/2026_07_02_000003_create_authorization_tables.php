<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->string('key', 120)->unique();
            $table->string('module', 80);
            $table->string('description', 500);
            $table->string('scope', 16);
            $table->string('risk_level', 16)->default('standard');
            $table->timestamps();
        });

        Schema::create('tenant_roles', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->boolean('is_system')->default(false);
            $table->char('created_by_user_id', 26);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'id'], 'tenant_roles_tenant_id_id_unique');
            $table->index(['tenant_id', 'created_at', 'id']);
        });

        Schema::create('tenant_role_permissions', function (Blueprint $table): void {
            $table->char('tenant_id', 26);
            $table->char('tenant_role_id', 26);
            $table->char('permission_id', 26);
            $table->char('granted_by_user_id', 26);
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['tenant_role_id', 'permission_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'tenant_role_id'], 'tenant_role_permissions_role_tenant_fk')
                ->references(['tenant_id', 'id'])->on('tenant_roles')->restrictOnDelete();
            $table->foreign('permission_id')->references('id')->on('permissions')->restrictOnDelete();
            $table->foreign('granted_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['tenant_id', 'permission_id']);
        });

        Schema::create('tenant_role_assignments', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('tenant_membership_id', 26);
            $table->char('tenant_role_id', 26);
            $table->char('granted_by_user_id', 26);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->char('revoked_by_user_id', 26)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'tenant_membership_id'], 'tenant_role_assignments_membership_tenant_fk')
                ->references(['tenant_id', 'id'])->on('tenant_memberships')->restrictOnDelete();
            $table->foreign(['tenant_id', 'tenant_role_id'], 'tenant_role_assignments_role_tenant_fk')
                ->references(['tenant_id', 'id'])->on('tenant_roles')->restrictOnDelete();
            $table->foreign('granted_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('revoked_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'tenant_membership_id', 'revoked_at', 'expires_at'], 'tenant_role_assignments_scope_idx');
        });

        Schema::create('platform_roles', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->string('name', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->boolean('is_system')->default(false);
            $table->char('created_by_user_id', 26);
            $table->timestamps();

            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('platform_role_permissions', function (Blueprint $table): void {
            $table->char('platform_role_id', 26);
            $table->char('permission_id', 26);
            $table->char('granted_by_user_id', 26);
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['platform_role_id', 'permission_id']);
            $table->foreign('platform_role_id')->references('id')->on('platform_roles')->restrictOnDelete();
            $table->foreign('permission_id')->references('id')->on('permissions')->restrictOnDelete();
            $table->foreign('granted_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('platform_role_assignments', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->char('platform_role_id', 26);
            $table->char('granted_by_user_id', 26);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->char('revoked_by_user_id', 26)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('platform_role_id')->references('id')->on('platform_roles')->restrictOnDelete();
            $table->foreign('granted_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('revoked_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['user_id', 'revoked_at', 'expires_at'], 'platform_role_assignments_scope_idx');
        });

        DB::statement("ALTER TABLE permissions ADD CONSTRAINT permissions_scope_chk CHECK (scope IN ('tenant', 'platform'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_role_assignments');
        Schema::dropIfExists('platform_role_permissions');
        Schema::dropIfExists('platform_roles');
        Schema::dropIfExists('tenant_role_assignments');
        Schema::dropIfExists('tenant_role_permissions');
        Schema::dropIfExists('tenant_roles');
        Schema::dropIfExists('permissions');
    }
};
