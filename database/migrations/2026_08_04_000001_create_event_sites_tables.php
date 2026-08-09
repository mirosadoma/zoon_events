<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_sites', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->string('status', 24)->default('draft');
            $table->string('page_mode', 16)->default('single');
            $table->json('draft_blocks');
            $table->json('settings')->nullable();
            $table->unsignedBigInteger('draft_updated_by_user_id')->nullable();
            $table->unsignedInteger('draft_revision')->default(1);
            $table->unsignedBigInteger('live_version_id')->nullable();
            $table->timestamp('published_at', 6)->nullable();
            $table->timestamp('unpublished_at', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['tenant_id', 'event_id'], 'event_sites_event_unique');
            $table->unique(['tenant_id', 'event_id', 'id'], 'event_sites_scope_unique');
            $table->index(['tenant_id', 'status'], 'event_sites_tenant_status_idx');
            $table->foreign(['tenant_id', 'event_id'], 'event_sites_event_fk')
                ->references(['tenant_id', 'id'])->on('events')->cascadeOnDelete();
            $table->foreign('draft_updated_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('event_site_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('event_site_id');
            $table->unsignedInteger('version');
            $table->string('status', 24)->default('published');
            $table->json('blocks');
            $table->char('blocks_hash', 64);
            $table->unsignedInteger('block_count')->default(0);
            $table->unsignedBigInteger('published_by_user_id')->nullable();
            $table->timestamp('published_at', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['tenant_id', 'event_site_id', 'version'], 'event_site_versions_number_unique');
            $table->unique(['tenant_id', 'event_id', 'id'], 'event_site_versions_scope_unique');
            $table->index(['tenant_id', 'event_id', 'status'], 'event_site_versions_status_idx');
            $table->foreign(['tenant_id', 'event_id', 'event_site_id'], 'event_site_versions_site_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('event_sites')->cascadeOnDelete();
            $table->foreign('published_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        if ($this->supportsCheckConstraints()) {
            DB::statement("ALTER TABLE event_sites ADD CONSTRAINT event_sites_status_chk CHECK (status IN ('draft','published','unpublished'))");
            DB::statement("ALTER TABLE event_sites ADD CONSTRAINT event_sites_page_mode_chk CHECK (page_mode IN ('single','multi'))");
            DB::statement("ALTER TABLE event_site_versions ADD CONSTRAINT event_site_versions_status_chk CHECK (status IN ('published','superseded'))");
            DB::statement("ALTER TABLE event_site_versions ADD CONSTRAINT event_site_versions_publish_chk CHECK ((status = 'published' AND published_at IS NOT NULL) OR status <> 'published')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_site_versions');
        Schema::dropIfExists('event_sites');
    }

    private function supportsCheckConstraints(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true);
    }
};
