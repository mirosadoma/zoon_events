<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_inventories', function (Blueprint $table): void {
            $table->char('tenant_id', 26);
            $table->char('event_id', 26);
            $table->char('ticket_type_id', 26);
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('held_quantity')->default(0);
            $table->unsignedInteger('sold_quantity')->default(0);
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps(6);

            $table->primary(['tenant_id', 'event_id', 'ticket_type_id']);
            $table->foreign(['tenant_id', 'event_id', 'ticket_type_id'], 'ticket_inventory_ticket_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('ticket_types')->restrictOnDelete();
            $table->index(['tenant_id', 'event_id', 'capacity', 'held_quantity', 'sold_quantity'], 'ticket_inventory_availability_idx');
        });

        Schema::create('price_tiers', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('event_id', 26);
            $table->char('ticket_type_id', 26);
            $table->string('name', 160);
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3);
            $table->timestamp('starts_at', 6)->nullable();
            $table->timestamp('ends_at', 6)->nullable();
            $table->unsignedInteger('remaining_at_most')->nullable();
            $table->unsignedInteger('priority');
            $table->string('status', 24)->default('draft');
            $table->timestamps(6);

            $table->unique(['tenant_id', 'event_id', 'ticket_type_id', 'id'], 'price_tiers_scope_unique');
            $table->unique(['tenant_id', 'ticket_type_id', 'priority', 'status'], 'price_tiers_priority_unique');
            $table->foreign(['tenant_id', 'event_id', 'ticket_type_id'], 'price_tiers_ticket_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('ticket_types')->restrictOnDelete();
            $table->index(['tenant_id', 'event_id', 'ticket_type_id', 'status', 'priority'], 'price_tiers_evaluation_idx');
        });

        Schema::create('inventory_holds', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('event_id', 26);
            $table->char('ticket_type_id', 26);
            $table->char('order_id', 26)->nullable()->unique();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('quoted_price_minor');
            $table->char('currency', 3);
            $table->char('price_tier_id', 26)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamp('expires_at', 6);
            $table->string('released_reason_code', 64)->nullable();
            $table->timestamps(6);

            $table->unique(['tenant_id', 'event_id', 'id'], 'inventory_holds_scope_unique');
            $table->foreign(['tenant_id', 'event_id', 'ticket_type_id'], 'inventory_holds_ticket_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('ticket_types')->restrictOnDelete();
            $table->index(['status', 'expires_at', 'id'], 'inventory_holds_expiry_idx');
            $table->index(['tenant_id', 'event_id', 'ticket_type_id', 'status'], 'inventory_holds_scope_status_idx');
        });

        DB::statement('ALTER TABLE ticket_inventories ADD CONSTRAINT ticket_inventory_counters_chk CHECK (capacity > 0 AND held_quantity + sold_quantity <= capacity)');
        DB::statement('ALTER TABLE price_tiers ADD CONSTRAINT price_tiers_selector_chk CHECK (starts_at IS NOT NULL OR ends_at IS NOT NULL OR remaining_at_most IS NOT NULL)');
        DB::statement('ALTER TABLE price_tiers ADD CONSTRAINT price_tiers_window_chk CHECK (starts_at IS NULL OR ends_at IS NULL OR ends_at > starts_at)');
        DB::statement('ALTER TABLE price_tiers ADD CONSTRAINT price_tiers_currency_chk CHECK (currency = UPPER(currency) AND CHAR_LENGTH(currency) = 3)');
        DB::statement("ALTER TABLE price_tiers ADD CONSTRAINT price_tiers_status_chk CHECK (status IN ('draft','active','retired'))");
        DB::statement('ALTER TABLE inventory_holds ADD CONSTRAINT inventory_holds_quantity_chk CHECK (quantity > 0)');
        DB::statement("ALTER TABLE inventory_holds ADD CONSTRAINT inventory_holds_status_chk CHECK (status IN ('active','converted','released','expired','reconciliation'))");
        DB::statement('ALTER TABLE inventory_holds ADD CONSTRAINT inventory_holds_currency_chk CHECK (currency = UPPER(currency) AND CHAR_LENGTH(currency) = 3)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_holds');
        Schema::dropIfExists('price_tiers');
        Schema::dropIfExists('ticket_inventories');
    }
};
