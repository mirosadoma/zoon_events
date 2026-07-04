<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('event_id', 26);
            $table->string('code', 64);
            $table->string('name_en', 160);
            $table->string('name_ar', 160);
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('attendee_type', 64);
            $table->unsignedBigInteger('base_price_minor')->default(0);
            $table->char('currency', 3);
            $table->timestamp('sale_starts_at', 6);
            $table->timestamp('sale_ends_at', 6);
            $table->string('status', 24)->default('draft');
            $table->char('created_by_user_id', 26);
            $table->timestamps(6);

            $table->unique(['tenant_id', 'event_id', 'id'], 'ticket_types_scope_unique');
            $table->unique(['tenant_id', 'event_id', 'code']);
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['tenant_id', 'event_id', 'status', 'sale_starts_at', 'sale_ends_at', 'id'], 'ticket_types_sale_index');
        });

        DB::statement('ALTER TABLE ticket_types ADD CONSTRAINT ticket_types_sale_window_chk CHECK (sale_ends_at > sale_starts_at)');
        DB::statement('ALTER TABLE ticket_types ADD CONSTRAINT ticket_types_currency_chk CHECK (currency = UPPER(currency) AND CHAR_LENGTH(currency) = 3)');
        DB::statement("ALTER TABLE ticket_types ADD CONSTRAINT ticket_types_status_chk CHECK (status IN ('draft','active','paused','sold_out','retired'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};
