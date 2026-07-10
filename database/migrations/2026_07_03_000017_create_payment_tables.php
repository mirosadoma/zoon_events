<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('adapter_key', 32);
            $table->string('secret_reference', 160);
            $table->string('account_reference', 160);
            $table->char('webhook_route_token_hash', 64);
            $table->string('mode', 16);
            $table->char('currency', 3);
            $table->string('status', 24)->default('draft');
            $table->timestamps(6);
            $table->unique(['tenant_id', 'id'], 'payment_accounts_scope_unique');
            $table->unique(['tenant_id', 'currency', 'mode', 'status'], 'payment_accounts_routing_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('payment_account_id')->nullable();
            $table->unsignedInteger('attempt_number');
            $table->text('provider_payment_id')->nullable();
            $table->char('provider_payment_id_hash', 64)->nullable();
            $table->char('idempotency_key_hash', 64);
            $table->string('status', 32);
            $table->unsignedBigInteger('requested_minor');
            $table->unsignedBigInteger('captured_minor')->default(0);
            $table->unsignedBigInteger('refunded_minor')->default(0);
            $table->char('currency', 3);
            $table->string('provider_reason_code', 64)->nullable();
            $table->timestamp('last_reconciled_at', 6)->nullable();
            $table->timestamp('next_reconcile_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['tenant_id', 'event_id', 'id'], 'payment_attempts_scope_unique');
            $table->unique(['tenant_id', 'order_id', 'attempt_number'], 'payment_attempts_number_unique');
            $table->unique(['payment_account_id', 'provider_payment_id_hash'], 'payment_attempts_provider_unique');
            $table->unique(['payment_account_id', 'idempotency_key_hash'], 'payment_attempts_idempotency_unique');
            $table->foreign(['tenant_id', 'event_id', 'order_id'], 'payment_attempts_order_fk')->references(['tenant_id', 'event_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'payment_account_id'], 'payment_attempts_account_fk')->references(['tenant_id', 'id'])->on('payment_accounts')->restrictOnDelete();
            $table->index(['status', 'next_reconcile_at', 'id'], 'payment_attempts_reconcile_index');
        });

        Schema::create('payment_webhook_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('payment_account_id')->nullable();
            $table->string('provider_event_id', 160);
            $table->char('payload_digest', 64);
            $table->string('status', 24);
            $table->string('reason_code', 64)->nullable();
            $table->timestamp('received_at', 6);
            $table->timestamp('processed_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['payment_account_id', 'provider_event_id'], 'payment_webhook_event_unique');
            $table->foreign('payment_account_id')->references('id')->on('payment_accounts')->restrictOnDelete();
            $table->index(['status', 'received_at', 'id'], 'payment_webhooks_processing_index');
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('payment_attempt_id')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 24);
            $table->string('reason', 500);
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->string('provider_refund_id', 160)->nullable();
            $table->char('idempotency_key_hash', 64);
            $table->timestamp('last_reconciled_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['tenant_id', 'event_id', 'id'], 'refunds_scope_unique');
            $table->unique(['payment_attempt_id', 'idempotency_key_hash'], 'refunds_idempotency_unique');
            $table->foreign(['tenant_id', 'event_id', 'order_id'], 'refunds_order_fk')->references(['tenant_id', 'event_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'payment_attempt_id'], 'refunds_attempt_fk')->references(['tenant_id', 'event_id', 'id'])->on('payment_attempts')->restrictOnDelete();
            $table->foreign('requested_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['status', 'last_reconciled_at', 'id'], 'refunds_reconcile_index');
        });

        DB::statement("ALTER TABLE payment_accounts ADD CONSTRAINT payment_accounts_mode_chk CHECK (mode IN ('test','live'))");
        DB::statement("ALTER TABLE payment_accounts ADD CONSTRAINT payment_accounts_status_chk CHECK (status IN ('draft','active','disabled'))");
        DB::statement("ALTER TABLE payment_attempts ADD CONSTRAINT payment_attempts_status_chk CHECK (status IN ('pending','authorized','captured','failed','cancelled','refunded','partially_refunded','unknown'))");
        DB::statement('ALTER TABLE payment_attempts ADD CONSTRAINT payment_attempts_money_chk CHECK (captured_minor <= requested_minor AND refunded_minor <= captured_minor)');
        DB::statement("ALTER TABLE payment_webhook_receipts ADD CONSTRAINT payment_webhooks_status_chk CHECK (status IN ('received','verified','processed','ignored','failed'))");
        DB::statement("ALTER TABLE refunds ADD CONSTRAINT refunds_status_chk CHECK (status IN ('pending','succeeded','failed','unknown'))");
        DB::statement('ALTER TABLE refunds ADD CONSTRAINT refunds_amount_chk CHECK (amount_minor > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_webhook_receipts');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payment_accounts');
    }
};
