<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_forms', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->string('name', 160);
            $table->string('status', 24)->default('draft');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps(6);

            $table->unique(['tenant_id', 'event_id', 'id'], 'registration_forms_scope_unique');
            $table->unique(['tenant_id', 'event_id', 'name']);
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('registration_form_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('registration_form_id')->nullable();
            $table->unsignedInteger('version');
            $table->string('status', 24)->default('draft');
            $table->json('fields');
            $table->char('schema_hash', 64);
            $table->string('privacy_notice_version', 64)->nullable();
            $table->string('terms_version', 64)->nullable();
            $table->unsignedBigInteger('published_by_user_id')->nullable();
            $table->timestamp('published_at', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['tenant_id', 'event_id', 'id'], 'registration_form_versions_scope_unique');
            $table->unique(['tenant_id', 'registration_form_id', 'version'], 'registration_form_versions_number_unique');
            $table->foreign(['tenant_id', 'event_id', 'registration_form_id'], 'registration_form_versions_form_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('registration_forms')->restrictOnDelete();
            $table->foreign('published_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['tenant_id', 'event_id', 'status']);
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'id', 'active_form_version_id'], 'events_active_form_version_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('registration_form_versions')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE registration_forms ADD CONSTRAINT registration_forms_status_chk CHECK (status IN ('draft','active','retired'))");
        DB::statement("ALTER TABLE registration_form_versions ADD CONSTRAINT registration_form_versions_status_chk CHECK (status IN ('draft','published','retired'))");
        DB::statement("ALTER TABLE registration_form_versions ADD CONSTRAINT registration_form_versions_publish_chk CHECK ((status = 'published' AND privacy_notice_version IS NOT NULL AND terms_version IS NOT NULL AND published_by_user_id IS NOT NULL AND published_at IS NOT NULL) OR status <> 'published')");
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropForeign('events_active_form_version_fk');
        });
        Schema::dropIfExists('registration_form_versions');
        Schema::dropIfExists('registration_forms');
    }
};
