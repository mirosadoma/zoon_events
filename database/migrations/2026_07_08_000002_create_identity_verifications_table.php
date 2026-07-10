<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('attendee_id')->nullable();
            $table->unsignedBigInteger('consent_id')->nullable();
            $table->string('method', 32)->default('gov_identity');
            $table->string('status', 32)->default('pending');
            $table->string('provider', 64)->nullable();
            $table->string('provider_reference', 160)->nullable();
            $table->string('verified_name', 160)->nullable();
            $table->string('verified_nationality', 16)->nullable();
            $table->timestamp('verified_at', 6)->nullable();
            $table->unsignedBigInteger('manual_review_by')->nullable();
            $table->timestamp('manual_review_at', 6)->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamp('retention_until', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['tenant_id', 'event_id', 'attendee_id'], 'identity_verifications_scope_unique');
            $table->foreign(['tenant_id', 'event_id', 'attendee_id'], 'identity_verifications_attendee_fk')
                ->references(['tenant_id', 'event_id', 'id'])
                ->on('attendees')
                ->restrictOnDelete();
            $table->foreign('manual_review_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'event_id', 'status'], 'identity_verifications_status_index');
        });

        DB::statement(
            "ALTER TABLE identity_verifications
            ADD CONSTRAINT identity_verifications_method_chk
            CHECK (method IN ('email_otp','phone_otp','gov_identity','face_capture','manual_review'))"
        );
        DB::statement(
            "ALTER TABLE identity_verifications
            ADD CONSTRAINT identity_verifications_status_chk
            CHECK (status IN ('not_required','pending','gov_verified','face_verified','manually_approved','rejected','expired'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_verifications');
    }
};
