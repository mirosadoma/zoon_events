<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_assets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('venue_id');
            $table->char('public_id', 26)->unique();
            $table->string('asset_type', 32);
            $table->string('name_en', 160);
            $table->string('name_ar', 160);
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('location_en', 240);
            $table->string('location_ar', 240);
            $table->json('capabilities');
            $table->unsignedInteger('capacity_per_minute')->nullable();
            $table->string('operational_status', 24)->default('draft');
            $table->string('pricing_model', 24);
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('retired_at', 6)->nullable();
            $table->unsignedBigInteger('created_by_user_id');
            $table->unsignedBigInteger('updated_by_user_id');
            $table->timestamps(6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'venue_id'])->references(['tenant_id', 'id'])->on('venues')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['tenant_id', 'id'], 'venue_assets_scope_unique');
            $table->unique(['tenant_id', 'venue_id', 'id'], 'venue_assets_venue_scope_unique');
            $table->index(['tenant_id', 'venue_id', 'operational_status', 'asset_type', 'id'], 'venue_assets_inventory_index');
        });

        Schema::create('venue_asset_bindings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('venue_asset_id');
            $table->string('control_family', 24);
            $table->string('adapter_key', 80)->nullable();
            $table->text('opaque_reference')->nullable();
            $table->text('binding_metadata')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('verified_at', 6)->nullable();
            $table->timestamp('disabled_at', 6)->nullable();
            $table->timestamps(6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'venue_asset_id'])->references(['tenant_id', 'id'])->on('venue_assets')->restrictOnDelete();
            $table->unique(['tenant_id', 'venue_asset_id'], 'venue_asset_bindings_asset_unique');
        });

        DB::statement("ALTER TABLE venue_assets ADD CONSTRAINT venue_assets_type_chk CHECK (asset_type IN ('turnstile','security_gate','camera','kiosk','printer','scanner','access_lane','access_zone'))");
        DB::statement("ALTER TABLE venue_assets ADD CONSTRAINT venue_assets_status_chk CHECK (operational_status IN ('draft','active','maintenance','offline','retired'))");
        DB::statement("ALTER TABLE venue_assets ADD CONSTRAINT venue_assets_pricing_chk CHECK (pricing_model IN ('per_hour','per_day','per_rental'))");
        DB::statement('ALTER TABLE venue_assets ADD CONSTRAINT venue_assets_currency_chk CHECK (currency = UPPER(currency) AND CHAR_LENGTH(currency) = 3)');
        DB::statement('ALTER TABLE venue_assets ADD CONSTRAINT venue_assets_version_chk CHECK (version >= 1)');
        DB::statement("ALTER TABLE venue_assets ADD CONSTRAINT venue_assets_retired_chk CHECK ((operational_status = 'retired' AND retired_at IS NOT NULL) OR (operational_status <> 'retired' AND retired_at IS NULL))");
        DB::statement("ALTER TABLE venue_asset_bindings ADD CONSTRAINT venue_asset_bindings_family_chk CHECK (control_family IN ('acs','kiosk','printer','scanner','catalog_only'))");
        DB::statement("ALTER TABLE venue_asset_bindings ADD CONSTRAINT venue_asset_bindings_status_chk CHECK (status IN ('active','disabled','invalid'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_asset_bindings');
        Schema::dropIfExists('venue_assets');
    }
};
