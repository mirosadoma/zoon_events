<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_disputes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organizer_tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('rental_request_id');
            $table->unsignedBigInteger('settlement_statement_id');
            $table->unsignedBigInteger('reported_by_tenant_id');
            $table->unsignedBigInteger('reported_by_user_id');
            $table->string('status', 24)->default('open');
            $table->string('reason_code', 80);
            $table->text('reason');
            $table->unsignedBigInteger('assigned_platform_user_id')->nullable();
            $table->string('resolution_code', 80)->nullable();
            $table->text('resolution_summary')->nullable();
            $table->timestamp('opened_at', 6);
            $table->timestamp('review_started_at', 6)->nullable();
            $table->timestamp('resolved_at', 6)->nullable();
            $table->timestamps(6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('organizer_tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'organizer_tenant_id', 'rental_request_id'],
                'marketplace_disputes_rental_fk',
            )->references(['tenant_id', 'organizer_tenant_id', 'id'])->on('rental_requests')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'organizer_tenant_id', 'settlement_statement_id'],
                'marketplace_disputes_statement_fk',
            )->references(['tenant_id', 'organizer_tenant_id', 'id'])->on('settlement_statements')->restrictOnDelete();
            $table->foreign('reported_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('assigned_platform_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(
                ['tenant_id', 'organizer_tenant_id', 'id'],
                'marketplace_disputes_participant_unique',
            );
            $table->index(
                ['status', 'opened_at', 'id'],
                'marketplace_disputes_platform_queue_index',
            );
            $table->index(
                ['tenant_id', 'organizer_tenant_id', 'settlement_statement_id', 'status'],
                'marketplace_disputes_statement_status_index',
            );
        });

        Schema::create('marketplace_dispute_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organizer_tenant_id');
            $table->unsignedBigInteger('marketplace_dispute_id');
            $table->string('event_type', 32);
            $table->string('actor_scope', 20);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('visibility', 20)->default('participants');
            $table->string('reason_code', 80)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at', 6);

            $table->foreign(
                ['tenant_id', 'organizer_tenant_id', 'marketplace_dispute_id'],
                'dispute_events_dispute_fk',
            )->references(['tenant_id', 'organizer_tenant_id', 'id'])->on('marketplace_disputes')->restrictOnDelete();
            $table->index(
                ['tenant_id', 'organizer_tenant_id', 'marketplace_dispute_id', 'created_at', 'id'],
                'dispute_events_timeline_index',
            );
        });

        DB::statement("ALTER TABLE marketplace_disputes ADD CONSTRAINT marketplace_disputes_status_chk CHECK (status IN ('open','under_review','resolved','rejected'))");
        DB::statement("ALTER TABLE marketplace_dispute_events ADD CONSTRAINT dispute_events_type_chk CHECK (event_type IN ('opened','review_started','note_added','resolved','rejected'))");
        DB::statement("ALTER TABLE marketplace_dispute_events ADD CONSTRAINT dispute_events_scope_chk CHECK (actor_scope IN ('owner','organizer','platform','system'))");
        DB::statement("ALTER TABLE marketplace_dispute_events ADD CONSTRAINT dispute_events_visibility_chk CHECK (visibility IN ('participants','platform_only'))");

        DB::statement('ALTER TABLE marketplace_disputes ADD active_statement_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status IN (\'open\',\'under_review\') THEN settlement_statement_id ELSE NULL END) STORED');
        DB::statement('CREATE UNIQUE INDEX marketplace_disputes_one_active_unique ON marketplace_disputes (tenant_id, organizer_tenant_id, active_statement_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_dispute_events');
        Schema::dropIfExists('marketplace_disputes');
    }
};
