<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_statements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organizer_tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('rental_request_id');
            $table->string('statement_number', 64)->unique();
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedBigInteger('supersedes_statement_id')->nullable();
            $table->string('status', 20)->default('issued');
            $table->string('dispute_status', 20)->default('none');
            $table->string('rental_outcome', 20);
            $table->string('venue_timezone', 64);
            $table->timestamp('agreed_start_at', 6);
            $table->timestamp('agreed_end_at', 6);
            $table->char('currency', 3);
            $table->unsignedBigInteger('agreed_total_minor');
            $table->timestamp('issued_at', 6);
            $table->string('generated_by', 20)->default('system');
            $table->timestamp('created_at', 6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('organizer_tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'organizer_tenant_id', 'rental_request_id'],
                'settlement_statements_rental_fk',
            )->references(['tenant_id', 'organizer_tenant_id', 'id'])->on('rental_requests')->restrictOnDelete();
            $table->foreign(
                'supersedes_statement_id',
                'settlement_statements_supersedes_fk',
            )->references('id')->on('settlement_statements')->restrictOnDelete();
            $table->unique(
                ['tenant_id', 'organizer_tenant_id', 'id'],
                'settlement_statements_participant_unique',
            );
            $table->unique(
                ['tenant_id', 'organizer_tenant_id', 'rental_request_id', 'revision'],
                'settlement_statements_revision_unique',
            );
            $table->index(
                ['tenant_id', 'status', 'created_at', 'id'],
                'settlement_statements_owner_index',
            );
            $table->index(
                ['organizer_tenant_id', 'status', 'created_at', 'id'],
                'settlement_statements_organizer_index',
            );
            $table->index(
                ['dispute_status', 'status', 'created_at', 'id'],
                'settlement_statements_platform_index',
            );
        });

        Schema::create('settlement_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organizer_tenant_id');
            $table->unsignedBigInteger('settlement_statement_id');
            $table->unsignedBigInteger('rental_asset_id');
            $table->char('publication_public_id', 26);
            $table->unsignedInteger('publication_version');
            $table->char('asset_public_id', 26);
            $table->string('asset_type', 32);
            $table->string('name_en', 160);
            $table->string('name_ar', 160);
            $table->string('pricing_model', 24);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedInteger('billable_units');
            $table->unsignedBigInteger('line_total_minor');
            $table->char('currency', 3);
            $table->timestamp('created_at', 6);

            $table->foreign(
                ['tenant_id', 'organizer_tenant_id', 'settlement_statement_id'],
                'statement_lines_statement_fk',
            )->references(['tenant_id', 'organizer_tenant_id', 'id'])->on('settlement_statements')->restrictOnDelete();
            $table->unique(
                ['tenant_id', 'organizer_tenant_id', 'settlement_statement_id', 'rental_asset_id'],
                'statement_lines_asset_unique',
            );
        });

        DB::statement("ALTER TABLE settlement_statements ADD CONSTRAINT settlement_statements_status_chk CHECK (status IN ('issued','superseded'))");
        DB::statement("ALTER TABLE settlement_statements ADD CONSTRAINT settlement_statements_dispute_chk CHECK (dispute_status IN ('none','open','under_review','resolved'))");
        DB::statement("ALTER TABLE settlement_statements ADD CONSTRAINT settlement_statements_outcome_chk CHECK (rental_outcome IN ('completed','cancelled','revoked'))");
        DB::statement('ALTER TABLE settlement_statements ADD CONSTRAINT settlement_statements_currency_chk CHECK (currency = UPPER(currency) AND CHAR_LENGTH(currency) = 3)');
        DB::statement('ALTER TABLE settlement_statements ADD CONSTRAINT settlement_statements_revision_chk CHECK (revision >= 1)');
        DB::statement('ALTER TABLE settlement_statements ADD CONSTRAINT settlement_statements_window_chk CHECK (agreed_end_at > agreed_start_at)');
        DB::statement("ALTER TABLE settlement_statement_lines ADD CONSTRAINT statement_lines_pricing_chk CHECK (pricing_model IN ('per_hour','per_day','per_rental'))");
        DB::statement('ALTER TABLE settlement_statement_lines ADD CONSTRAINT statement_lines_values_chk CHECK (billable_units >= 1)');
        DB::statement('ALTER TABLE settlement_statement_lines ADD CONSTRAINT statement_lines_currency_chk CHECK (currency = UPPER(currency) AND CHAR_LENGTH(currency) = 3)');
        DB::statement('ALTER TABLE settlement_statement_lines ADD CONSTRAINT statement_lines_total_chk CHECK (line_total_minor = unit_price_minor * billable_units)');
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_statement_lines');
        Schema::dropIfExists('settlement_statements');
    }
};
