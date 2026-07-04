<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_submissions', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('event_id', 26);
            $table->char('form_version_id', 26);
            $table->char('submission_key_hash', 64);
            $table->longText('answers_ciphertext');
            $table->string('encryption_key_id', 64);
            $table->json('consent_evidence');
            $table->char('locale', 2);
            $table->timestamp('submitted_at', 6);
            $table->timestamp('created_at', 6)->nullable();

            $table->unique(['tenant_id', 'event_id', 'id'], 'registration_submissions_scope_unique');
            $table->unique(['tenant_id', 'event_id', 'submission_key_hash'], 'registration_submissions_key_unique');
            $table->foreign(['tenant_id', 'event_id', 'form_version_id'], 'registration_submissions_form_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('registration_form_versions')->restrictOnDelete();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('event_id', 26);
            $table->string('public_reference', 80);
            $table->char('access_token_hash', 64);
            $table->string('status', 32);
            $table->text('buyer_name_ciphertext');
            $table->text('buyer_email_ciphertext');
            $table->text('buyer_phone_ciphertext')->nullable();
            $table->char('buyer_email_index', 64);
            $table->char('buyer_phone_index', 64)->nullable();
            $table->string('encryption_key_id', 64);
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('fees_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->char('currency', 3);
            $table->char('inventory_hold_id', 26);
            $table->char('locale', 2);
            $table->timestamp('paid_at', 6)->nullable();
            $table->timestamp('cancelled_at', 6)->nullable();
            $table->timestamp('refunded_at', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['tenant_id', 'event_id', 'id'], 'orders_scope_unique');
            $table->unique(['tenant_id', 'public_reference']);
            $table->unique('access_token_hash');
            $table->unique('inventory_hold_id');
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'inventory_hold_id'], 'orders_hold_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('inventory_holds')->restrictOnDelete();
            $table->index(['tenant_id', 'event_id', 'status', 'created_at', 'id'], 'orders_status_index');
            $table->index(['tenant_id', 'event_id', 'buyer_email_index', 'created_at', 'id'], 'orders_email_index');
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('event_id', 26);
            $table->char('order_id', 26);
            $table->char('ticket_type_id', 26);
            $table->char('attendee_id', 26)->nullable()->unique();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('fees_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->char('currency', 3);
            $table->char('price_tier_id', 26)->nullable();
            $table->json('ticket_name_snapshot');
            $table->timestamps(6);

            $table->unique(['tenant_id', 'event_id', 'id'], 'order_items_scope_unique');
            $table->foreign(['tenant_id', 'event_id', 'order_id'], 'order_items_order_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'ticket_type_id'], 'order_items_ticket_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('ticket_types')->restrictOnDelete();
            $table->index(['tenant_id', 'event_id', 'order_id']);
        });

        Schema::table('inventory_holds', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'event_id', 'order_id'], 'inventory_holds_order_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('orders')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE registration_submissions ADD CONSTRAINT registration_submissions_locale_chk CHECK (locale IN ('en','ar'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_chk CHECK (status IN ('draft','pending_payment','paid','failed','cancelled','refunded','partially_refunded'))");
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_money_chk CHECK (subtotal_minor + tax_minor + fees_minor = total_minor)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_currency_chk CHECK (currency = UPPER(currency) AND CHAR_LENGTH(currency) = 3)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_chk CHECK (quantity > 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_money_chk CHECK (unit_price_minor * quantity + tax_minor + fees_minor = total_minor)');
    }

    public function down(): void
    {
        Schema::table('inventory_holds', fn (Blueprint $table) => $table->dropForeign('inventory_holds_order_fk'));
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('registration_submissions');
    }
};
