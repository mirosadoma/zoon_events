<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->string('name', 160);
            $table->string('email', 254)->unique();
            $table->string('password');
            $table->string('status', 24)->default('active');
            $table->string('preferred_locale', 10)->default('en');
            $table->timestamp('last_authenticated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->char('created_by_user_id', 26)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->index(['status', 'created_at', 'id']);
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_lifecycle_chk CHECK (
            (status = 'active' AND suspended_at IS NULL AND deactivated_at IS NULL)
            OR (status = 'suspended' AND suspended_at IS NOT NULL AND deactivated_at IS NULL)
            OR (status = 'deactivated' AND deactivated_at IS NOT NULL)
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
