<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_print_jobs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('attendee_id')->nullable();
            $table->unsignedBigInteger('credential_id')->nullable();
            $table->unsignedBigInteger('badge_template_id')->nullable();
            // kiosk_id FK to kiosks is deferred to T071; table doesn't exist yet.
            $table->unsignedBigInteger('kiosk_id')->nullable();
            $table->unsignedBigInteger('printed_by_user_id')->nullable();
            $table->string('status', 20)->default('queued');
            $table->string('failure_reason', 60)->nullable();
            $table->boolean('is_reprint')->default(false);
            $table->string('reprint_reason', 500)->nullable();
            $table->unsignedBigInteger('original_print_job_id')->nullable();
            $table->timestamp('printed_at', 6)->nullable();
            $table->timestamps(6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'attendee_id'])->references(['tenant_id', 'event_id', 'id'])->on('attendees')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'credential_id'])->references(['tenant_id', 'event_id', 'id'])->on('credentials')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'badge_template_id'])->references(['tenant_id', 'event_id', 'id'])->on('badge_templates')->restrictOnDelete();
            $table->foreign('printed_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('original_print_job_id')->references('id')->on('badge_print_jobs')->nullOnDelete();

            $table->index(['tenant_id', 'event_id', 'attendee_id', 'created_at']);
            $table->index(['tenant_id', 'event_id', 'status']);
        });

        DB::statement("ALTER TABLE badge_print_jobs ADD CONSTRAINT badge_print_jobs_status_chk CHECK (status IN ('queued','printed','failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_print_jobs');
    }
};
