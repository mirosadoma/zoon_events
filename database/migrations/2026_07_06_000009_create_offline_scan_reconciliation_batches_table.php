<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_scan_reconciliation_batches', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('event_id', 26);
            $table->string('device_reference', 120);
            $table->timestamp('allowlist_issued_at', 6);
            $table->timestamp('allowlist_expires_at', 6);
            $table->unsignedInteger('submitted_scan_count')->default(0);
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('conflict_count')->default(0);
            $table->string('status', 32)->default('received');
            $table->timestamp('created_at', 6)->useCurrent();
            $table->timestamp('processed_at', 6)->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();
            $table->index(['tenant_id', 'event_id', 'processed_at'], 'offline_scan_batches_event_timeline_index');
        });

        DB::statement("ALTER TABLE offline_scan_reconciliation_batches ADD CONSTRAINT offline_scan_batches_status_chk CHECK (status IN ('received','processed','processed_with_conflicts'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_scan_reconciliation_batches');
    }
};
