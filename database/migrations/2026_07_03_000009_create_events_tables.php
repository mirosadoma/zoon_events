<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->string('slug', 100);
            $table->string('name_en', 160);
            $table->string('name_ar', 160);
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('tier', 24);
            $table->string('status', 32)->default('draft');
            $table->string('timezone', 64);
            $table->timestamp('start_at', 6);
            $table->timestamp('end_at', 6);
            $table->timestamp('registration_opens_at', 6);
            $table->timestamp('registration_closes_at', 6);
            $table->string('location_name_en', 200)->nullable();
            $table->string('location_name_ar', 200)->nullable();
            $table->string('location_address_en', 500)->nullable();
            $table->string('location_address_ar', 500)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->char('active_form_version_id', 26)->nullable();
            $table->char('created_by_user_id', 26);
            $table->char('published_by_user_id', 26)->nullable();
            $table->timestamp('published_at', 6)->nullable();
            $table->timestamp('cancelled_at', 6)->nullable();
            $table->timestamp('archived_at', 6)->nullable();
            $table->timestamps(6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('published_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['tenant_id', 'id'], 'events_tenant_id_id_unique');
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status', 'start_at', 'id']);
            $table->index(['tenant_id', 'registration_opens_at', 'registration_closes_at'], 'events_registration_window_index');
        });

        Schema::create('event_branding', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('event_id', 26);
            $table->string('brand_reference', 120);
            $table->string('domain_reference', 255);
            $table->json('content_en');
            $table->json('content_ar');
            $table->string('sender_name_en', 160);
            $table->string('sender_name_ar', 160);
            $table->string('status', 24)->default('draft');
            $table->timestamps(6);

            $table->unique(['tenant_id', 'event_id']);
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->index(['tenant_id', 'domain_reference', 'status']);
        });

        DB::statement('ALTER TABLE events ADD CONSTRAINT events_schedule_chk CHECK (end_at > start_at AND registration_closes_at > registration_opens_at AND registration_closes_at <= end_at)');
        DB::statement("ALTER TABLE events ADD CONSTRAINT events_tier_chk CHECK (tier IN ('corporate','public','vip','vvip'))");
        DB::statement("ALTER TABLE events ADD CONSTRAINT events_status_chk CHECK (status IN ('draft','configured','published','registration_open','registration_closed','live','completed','cancelled','archived'))");
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_capacity_chk CHECK (capacity IS NULL OR capacity > 0)');
        DB::statement("ALTER TABLE event_branding ADD CONSTRAINT event_branding_status_chk CHECK (status IN ('draft','active','retired'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('event_branding');
        Schema::dropIfExists('events');
    }
};
