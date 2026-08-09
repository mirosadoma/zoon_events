<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_insight_summaries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->string('metric_window', 32);
            $table->char('payload_hash', 64);
            $table->json('metrics_payload');
            $table->text('summary_en')->nullable();
            $table->text('summary_ar')->nullable();
            $table->json('highlights')->nullable();
            $table->string('provider_key', 32);
            $table->unsignedBigInteger('generated_by_user_id')->nullable();
            $table->timestamp('generated_at', 6);
            $table->timestamp('expires_at', 6);
            $table->timestamps(6);

            $table->unique(
                ['tenant_id', 'event_id', 'metric_window', 'payload_hash'],
                'event_insight_summaries_cache_unique',
            );
            $table->index(['tenant_id', 'event_id', 'generated_at'], 'event_insight_summaries_recent_idx');
            $table->foreign(['tenant_id', 'event_id'], 'event_insight_summaries_event_fk')
                ->references(['tenant_id', 'id'])->on('events')->cascadeOnDelete();
            $table->foreign('generated_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_insight_summaries');
    }
};
