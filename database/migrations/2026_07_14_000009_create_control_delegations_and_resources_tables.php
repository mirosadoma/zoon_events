<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_delegations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organizer_tenant_id');
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('rental_request_id');
            $table->unsignedBigInteger('event_id');
            $table->string('status', 20)->default('pending');
            $table->timestamp('starts_at', 6);
            $table->timestamp('ends_at', 6);
            $table->timestamp('revoked_at', 6)->nullable();
            $table->timestamp('expired_at', 6)->nullable();
            $table->timestamp('completed_at', 6)->nullable();
            $table->unsignedBigInteger('revoked_by_user_id')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->string('degraded_reason_code', 80)->nullable();
            $table->unsignedInteger('provision_attempts')->default(0);
            $table->timestamp('last_provision_attempt_at', 6)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->char('idempotency_key_hash', 64);
            $table->timestamps(6);

            $table->foreign(
                ['tenant_id', 'organizer_tenant_id', 'rental_request_id'],
                'control_delegations_request_fk',
            )->references(['tenant_id', 'organizer_tenant_id', 'id'])
                ->on('rental_requests')->restrictOnDelete();
            $table->foreign(['organizer_tenant_id', 'event_id'])
                ->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign('revoked_by_user_id')
                ->references('id')->on('users')->restrictOnDelete();
            $table->unique(
                ['tenant_id', 'organizer_tenant_id', 'rental_request_id'],
                'control_delegations_request_unique',
            );
            $table->unique(
                ['tenant_id', 'organizer_tenant_id', 'id'],
                'control_delegations_participant_unique',
            );
            $table->unique(
                ['tenant_id', 'rental_request_id', 'idempotency_key_hash'],
                'control_delegations_idempotency_unique',
            );
            $table->index(
                ['tenant_id', 'status', 'starts_at', 'ends_at', 'id'],
                'control_delegations_lifecycle_index',
            );
            $table->index(
                ['organizer_tenant_id', 'event_id', 'status', 'id'],
                'control_delegations_organizer_event_index',
            );
        });

        DB::statement("ALTER TABLE control_delegations ADD CONSTRAINT control_delegations_status_chk CHECK (status IN ('pending','active','degraded','revoked','expired','completed'))");
        DB::statement('ALTER TABLE control_delegations ADD CONSTRAINT control_delegations_window_chk CHECK (ends_at > starts_at)');
        DB::statement('ALTER TABLE control_delegations ADD CONSTRAINT control_delegations_version_chk CHECK (version >= 1)');
        DB::statement("ALTER TABLE control_delegations ADD CONSTRAINT control_delegations_revocation_chk CHECK ((status = 'revoked' AND revoked_at IS NOT NULL AND revoked_by_user_id IS NOT NULL AND revocation_reason IS NOT NULL) OR status <> 'revoked')");

        Schema::create('delegated_asset_resources', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organizer_tenant_id');
            $table->unsignedBigInteger('control_delegation_id');
            $table->unsignedBigInteger('rental_request_id');
            $table->unsignedBigInteger('rental_asset_id');
            $table->unsignedBigInteger('venue_asset_id');
            $table->string('resource_module', 32);
            $table->string('resource_type', 48);
            $table->char('resource_public_reference', 26)->nullable();
            $table->json('granted_capabilities');
            $table->string('provisioning_status', 20)->default('pending');
            $table->string('failure_reason_code', 80)->nullable();
            $table->timestamp('provisioned_at', 6)->nullable();
            $table->timestamp('released_at', 6)->nullable();
            $table->char('idempotency_key_hash', 64);
            $table->timestamps(6);

            $table->foreign(
                ['tenant_id', 'organizer_tenant_id', 'control_delegation_id'],
                'delegated_resources_delegation_fk',
            )->references(['tenant_id', 'organizer_tenant_id', 'id'])
                ->on('control_delegations')->restrictOnDelete();
            $table->foreign(
                ['tenant_id', 'organizer_tenant_id', 'rental_request_id'],
                'delegated_resources_request_fk',
            )->references(['tenant_id', 'organizer_tenant_id', 'id'])
                ->on('rental_requests')->restrictOnDelete();
            $table->foreign('rental_asset_id')
                ->references('id')->on('rental_assets')->restrictOnDelete();
            $table->foreign(['tenant_id', 'venue_asset_id'])
                ->references(['tenant_id', 'id'])->on('venue_assets')->restrictOnDelete();
            $table->unique(
                ['tenant_id', 'organizer_tenant_id', 'control_delegation_id', 'rental_asset_id'],
                'delegated_resources_rental_asset_unique',
            );
            $table->unique(
                ['tenant_id', 'control_delegation_id', 'idempotency_key_hash'],
                'delegated_resources_idempotency_unique',
            );
            $table->index(
                ['organizer_tenant_id', 'control_delegation_id', 'provisioning_status'],
                'delegated_resources_participant_index',
            );
        });

        DB::statement("ALTER TABLE delegated_asset_resources ADD CONSTRAINT delegated_resources_module_chk CHECK (resource_module IN ('access_control','kiosk','badge_printing','scanning','catalog_only'))");
        DB::statement("ALTER TABLE delegated_asset_resources ADD CONSTRAINT delegated_resources_status_chk CHECK (provisioning_status IN ('pending','provisioned','degraded','released','not_applicable'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('delegated_asset_resources');
        Schema::dropIfExists('control_delegations');
    }
};
