<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_biometric_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('verification_id')->nullable();
            $table->string('artifact_type', 24)->default('template');
            $table->text('storage_reference');
            $table->string('liveness_result', 24)->nullable();
            $table->timestamp('retention_until', 6);
            $table->timestamp('created_at', 6);
            $table->timestamp('purged_at', 6)->nullable();

            $table->foreign('verification_id', 'identity_artifacts_verification_fk')
                ->references('id')
                ->on('identity_verifications')
                ->restrictOnDelete();
            $table->index(['tenant_id', 'retention_until', 'purged_at'], 'identity_artifacts_retention_index');
        });

        DB::statement(
            "ALTER TABLE identity_biometric_artifacts
            ADD CONSTRAINT identity_artifacts_type_chk
            CHECK (artifact_type IN ('template','image'))"
        );
        DB::statement(
            "ALTER TABLE identity_biometric_artifacts
            ADD CONSTRAINT identity_artifacts_liveness_chk
            CHECK (liveness_result IS NULL OR liveness_result IN ('passed','failed','unavailable'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_biometric_artifacts');
    }
};
