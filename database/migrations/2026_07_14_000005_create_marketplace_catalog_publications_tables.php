<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_catalog_publications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('venue_id');
            $table->unsignedBigInteger('venue_asset_id');
            $table->char('venue_public_id', 26);
            $table->char('asset_public_id', 26);
            $table->unsignedInteger('publication_version');
            $table->unsignedInteger('venue_version');
            $table->unsignedInteger('asset_version');
            $table->string('venue_name_en', 160);
            $table->string('venue_name_ar', 160);
            $table->text('venue_description_en')->nullable();
            $table->text('venue_description_ar')->nullable();
            $table->string('asset_name_en', 160);
            $table->string('asset_name_ar', 160);
            $table->text('asset_description_en')->nullable();
            $table->text('asset_description_ar')->nullable();
            $table->string('address_en', 500);
            $table->string('address_ar', 500);
            $table->char('country_code', 2);
            $table->string('city_code', 80);
            $table->string('timezone', 64);
            $table->string('asset_type', 32);
            $table->string('location_en', 240);
            $table->string('location_ar', 240);
            $table->unsignedInteger('capacity_per_minute')->nullable();
            $table->string('pricing_model', 24);
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3);
            $table->json('availability_windows');
            $table->json('public_contact')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('published_at', 6);
            $table->timestamp('withdrawn_at', 6)->nullable();
            $table->timestamps(6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'venue_id'])->references(['tenant_id', 'id'])->on('venues')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'venue_id', 'venue_asset_id'],
                'catalog_publications_asset_fk',
            )->references(['tenant_id', 'venue_id', 'id'])->on('venue_assets')->restrictOnDelete();
            $table->unique(['tenant_id', 'id'], 'catalog_publications_scope_unique');
            $table->unique(['tenant_id', 'venue_asset_id', 'publication_version'], 'catalog_publications_version_unique');
            $table->index(
                ['status', 'country_code', 'city_code', 'asset_type', 'currency', 'price_minor', 'id'],
                'catalog_publications_search_index',
            );
            $table->index(['status', 'capacity_per_minute', 'id'], 'catalog_publications_capacity_index');
        });

        Schema::create('marketplace_publication_capabilities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('catalog_publication_id');
            $table->string('capability_code', 80);

            $table->foreign(
                ['tenant_id', 'catalog_publication_id'],
                'publication_capabilities_publication_fk',
            )
                ->references(['tenant_id', 'id'])
                ->on('marketplace_catalog_publications')
                ->cascadeOnDelete();
            $table->unique(
                ['tenant_id', 'catalog_publication_id', 'capability_code'],
                'publication_capabilities_unique',
            );
            $table->index(['capability_code', 'catalog_publication_id'], 'publication_capabilities_search_index');
        });

        Schema::create('marketplace_publication_availability_windows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('catalog_publication_id');
            $table->timestamp('available_from', 6);
            $table->timestamp('available_until', 6);

            $table->foreign(
                ['tenant_id', 'catalog_publication_id'],
                'publication_availability_publication_fk',
            )
                ->references(['tenant_id', 'id'])
                ->on('marketplace_catalog_publications')
                ->cascadeOnDelete();
            $table->unique(
                ['tenant_id', 'catalog_publication_id', 'available_from', 'available_until'],
                'publication_availability_unique',
            );
            $table->index(
                ['available_from', 'available_until', 'catalog_publication_id'],
                'publication_availability_search_index',
            );
        });

        DB::statement("ALTER TABLE marketplace_catalog_publications ADD CONSTRAINT catalog_publications_status_chk CHECK (status IN ('active','withdrawn'))");
        DB::statement("ALTER TABLE marketplace_catalog_publications ADD CONSTRAINT catalog_publications_lifecycle_chk CHECK ((status = 'active' AND withdrawn_at IS NULL) OR (status = 'withdrawn' AND withdrawn_at IS NOT NULL))");
        DB::statement('ALTER TABLE marketplace_catalog_publications ADD active_venue_asset_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = \'active\' THEN venue_asset_id ELSE NULL END) STORED');
        DB::statement('CREATE UNIQUE INDEX catalog_publications_one_active_unique ON marketplace_catalog_publications (tenant_id, active_venue_asset_id)');
        DB::statement('ALTER TABLE marketplace_publication_availability_windows ADD CONSTRAINT publication_availability_interval_chk CHECK (available_until > available_from)');
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_publication_availability_windows');
        Schema::dropIfExists('marketplace_publication_capabilities');
        Schema::dropIfExists('marketplace_catalog_publications');
    }
};
