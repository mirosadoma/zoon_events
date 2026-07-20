<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organizer_tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('venue_id');
            $table->char('venue_public_id', 26);
            $table->string('venue_name_en', 160);
            $table->string('venue_name_ar', 160);
            $table->string('status', 24)->default('requested');
            $table->string('dispute_status', 20)->default('none');
            $table->timestamp('requested_start_at', 6);
            $table->timestamp('requested_end_at', 6);
            $table->string('venue_timezone', 64);
            $table->char('quote_digest', 64);
            $table->unsignedInteger('quote_version');
            $table->json('event_snapshot');
            $table->char('currency', 3);
            $table->unsignedBigInteger('total_minor');
            $table->char('idempotency_key_hash', 64);
            $table->char('idempotency_payload_hash', 64);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->unsignedBigInteger('owner_decided_by_user_id')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamp('submitted_at', 6);
            $table->timestamp('approved_at', 6)->nullable();
            $table->timestamp('rejected_at', 6)->nullable();
            $table->timestamp('activated_at', 6)->nullable();
            $table->timestamp('completed_at', 6)->nullable();
            $table->timestamp('cancelled_at', 6)->nullable();
            $table->timestamp('revoked_at', 6)->nullable();
            $table->timestamps(6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('organizer_tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['organizer_tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(['tenant_id', 'venue_id'])->references(['tenant_id', 'id'])->on('venues')->restrictOnDelete();
            $table->foreign('submitted_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('owner_decided_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['tenant_id', 'organizer_tenant_id', 'id'], 'rental_requests_participant_unique');
            $table->unique(
                ['organizer_tenant_id', 'submitted_by_user_id', 'idempotency_key_hash'],
                'rental_requests_idempotency_unique',
            );
            $table->index(['tenant_id', 'status', 'requested_start_at', 'id'], 'rental_requests_owner_index');
            $table->index(['organizer_tenant_id', 'status', 'requested_start_at', 'id'], 'rental_requests_organizer_index');
            $table->index(['dispute_status', 'status', 'created_at', 'id'], 'rental_requests_platform_index');
        });

        DB::statement("ALTER TABLE rental_requests ADD CONSTRAINT rental_requests_status_chk CHECK (status IN ('requested','approved','rejected','active','completed','cancelled','revoked'))");
        DB::statement("ALTER TABLE rental_requests ADD CONSTRAINT rental_requests_dispute_chk CHECK (dispute_status IN ('none','open','under_review','resolved'))");
        DB::statement('ALTER TABLE rental_requests ADD CONSTRAINT rental_requests_window_chk CHECK (requested_end_at > requested_start_at)');
        DB::statement('ALTER TABLE rental_requests ADD CONSTRAINT rental_requests_currency_chk CHECK (currency = UPPER(currency) AND CHAR_LENGTH(currency) = 3)');
        DB::statement('ALTER TABLE rental_requests ADD CONSTRAINT rental_requests_version_chk CHECK (version >= 1 AND quote_version >= 1)');
        DB::statement('ALTER TABLE rental_requests ADD CONSTRAINT rental_requests_participants_chk CHECK (tenant_id <> organizer_tenant_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_requests');
    }
};
