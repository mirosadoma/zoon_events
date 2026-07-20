<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->string('name_en', 160);
            $table->string('name_ar', 160);
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('address_en', 500);
            $table->string('address_ar', 500);
            $table->char('country_code', 2);
            $table->string('city_code', 80);
            $table->string('timezone', 64);
            $table->string('business_contact_name', 160)->nullable();
            $table->string('business_contact_email', 254)->nullable();
            $table->string('business_contact_phone', 32)->nullable();
            $table->boolean('publish_contact')->default(false);
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('activated_at', 6)->nullable();
            $table->timestamp('suspended_at', 6)->nullable();
            $table->timestamp('archived_at', 6)->nullable();
            $table->unsignedBigInteger('created_by_user_id');
            $table->unsignedBigInteger('updated_by_user_id');
            $table->timestamps(6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['tenant_id', 'id'], 'venues_scope_unique');
            $table->index(['tenant_id', 'status', 'id'], 'venues_status_index');
            $table->index(['tenant_id', 'country_code', 'city_code', 'status', 'id'], 'venues_location_index');
        });

        DB::statement("ALTER TABLE venues ADD CONSTRAINT venues_status_chk CHECK (status IN ('draft','active','suspended','archived'))");
        DB::statement('ALTER TABLE venues ADD CONSTRAINT venues_version_chk CHECK (version >= 1)');
        DB::statement('ALTER TABLE venues ADD CONSTRAINT venues_country_chk CHECK (country_code = UPPER(country_code) AND CHAR_LENGTH(country_code) = 2)');
        DB::statement("ALTER TABLE venues ADD CONSTRAINT venues_lifecycle_chk CHECK (
            (status = 'draft' AND activated_at IS NULL AND suspended_at IS NULL AND archived_at IS NULL)
            OR (status = 'active' AND activated_at IS NOT NULL AND suspended_at IS NULL AND archived_at IS NULL)
            OR (status = 'suspended' AND activated_at IS NOT NULL AND suspended_at IS NOT NULL AND archived_at IS NULL)
            OR (status = 'archived' AND archived_at IS NOT NULL)
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
