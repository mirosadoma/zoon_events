<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_signing_keys', function (Blueprint $table): void {
            $table->string('key_id', 64)->primary();
            $table->string('public_key', 128);
            $table->string('private_key_reference', 160)->nullable();
            $table->string('status', 24);
            $table->timestamp('not_before', 6)->nullable();
            $table->timestamp('verify_until', 6)->nullable();
            $table->timestamps(6);
        });

        Schema::create('credentials', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('event_id', 26);
            $table->char('attendee_id', 26);
            $table->char('ticket_type_id', 26);
            $table->string('status', 24)->default('active');
            $table->string('token_version', 16);
            $table->string('key_id', 64);
            $table->char('nonce_hash', 64)->unique();
            $table->char('token_digest', 64)->unique();
            $table->timestamp('issued_at', 6);
            $table->timestamp('expires_at', 6);
            $table->timestamp('revoked_at', 6)->nullable();
            $table->char('revoked_by_user_id', 26)->nullable();
            $table->string('revocation_reason', 500)->nullable();
            $table->char('superseded_by_id', 26)->nullable();
            $table->char('active_attendee_id', 26)->nullable()->virtualAs("IF(status = 'active', attendee_id, NULL)");
            $table->timestamps(6);

            $table->unique(['tenant_id', 'event_id', 'id'], 'credentials_scope_unique');
            $table->unique(['tenant_id', 'event_id', 'active_attendee_id'], 'credentials_one_active_unique');
            $table->foreign(['tenant_id', 'event_id', 'attendee_id'], 'credentials_attendee_fk')->references(['tenant_id', 'event_id', 'id'])->on('attendees')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'ticket_type_id'], 'credentials_ticket_fk')->references(['tenant_id', 'event_id', 'id'])->on('ticket_types')->restrictOnDelete();
            $table->foreign('revoked_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('key_id')->references('key_id')->on('credential_signing_keys')->restrictOnDelete();
            $table->index(['tenant_id', 'event_id', 'status', 'expires_at', 'id'], 'credentials_status_index');
        });

        Schema::table('credentials', fn (Blueprint $table) => $table->foreign(['tenant_id', 'event_id', 'superseded_by_id'], 'credentials_superseded_fk')->references(['tenant_id', 'event_id', 'id'])->on('credentials')->restrictOnDelete());
        DB::statement("ALTER TABLE credential_signing_keys ADD CONSTRAINT credential_keys_status_chk CHECK (status IN ('pending','active','verify_only','retired','compromised'))");
        DB::statement("ALTER TABLE credentials ADD CONSTRAINT credentials_status_chk CHECK (status IN ('active','revoked','expired','superseded'))");
        DB::statement('ALTER TABLE credentials ADD CONSTRAINT credentials_expiry_chk CHECK (expires_at > issued_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('credentials');
        Schema::dropIfExists('credential_signing_keys');
    }
};
