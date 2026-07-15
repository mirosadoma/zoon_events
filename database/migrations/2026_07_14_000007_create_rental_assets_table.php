<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_assets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organizer_tenant_id');
            $table->unsignedBigInteger('rental_request_id');
            $table->unsignedBigInteger('venue_asset_id');
            $table->char('asset_public_id', 26);
            $table->unsignedBigInteger('catalog_publication_id');
            $table->char('publication_public_id', 26);
            $table->unsignedInteger('publication_version');
            $table->string('asset_type', 32);
            $table->string('name_en', 160);
            $table->string('name_ar', 160);
            $table->json('capabilities');
            $table->json('selected_capabilities');
            $table->string('pricing_model', 24);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('billable_units');
            $table->unsignedBigInteger('line_total_minor');
            $table->char('currency', 3);
            $table->unsignedInteger('line_order');
            $table->timestamp('created_at', 6);

            $table->foreign(
                ['tenant_id', 'organizer_tenant_id', 'rental_request_id'],
                'rental_assets_request_fk',
            )->references(['tenant_id', 'organizer_tenant_id', 'id'])->on('rental_requests')->restrictOnDelete();
            $table->foreign(['tenant_id', 'venue_asset_id'])->references(['tenant_id', 'id'])->on('venue_assets')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'catalog_publication_id'],
                'rental_assets_publication_fk',
            )->references(['tenant_id', 'id'])->on('marketplace_catalog_publications')->restrictOnDelete();
            $table->unique(
                ['tenant_id', 'organizer_tenant_id', 'rental_request_id', 'venue_asset_id'],
                'rental_assets_request_asset_unique',
            );
            $table->unique(
                ['tenant_id', 'organizer_tenant_id', 'rental_request_id', 'line_order'],
                'rental_assets_request_order_unique',
            );
            $table->index(['tenant_id', 'venue_asset_id', 'rental_request_id'], 'rental_assets_owner_asset_index');
        });

        DB::statement("ALTER TABLE rental_assets ADD CONSTRAINT rental_assets_pricing_chk CHECK (pricing_model IN ('per_hour','per_day','per_rental'))");
        DB::statement('ALTER TABLE rental_assets ADD CONSTRAINT rental_assets_values_chk CHECK (quantity >= 1 AND billable_units >= 1 AND line_order >= 1)');
        DB::statement('ALTER TABLE rental_assets ADD CONSTRAINT rental_assets_currency_chk CHECK (currency = UPPER(currency) AND CHAR_LENGTH(currency) = 3)');
        DB::statement('ALTER TABLE rental_assets ADD CONSTRAINT rental_assets_total_chk CHECK (line_total_minor = unit_price_minor * billable_units * quantity)');
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_assets');
    }
};
