<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_assistant_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->boolean('enabled')->default(false);
            $table->string('display_name_en', 120)->nullable();
            $table->string('display_name_ar', 120)->nullable();
            $table->string('greeting_en', 500)->nullable();
            $table->string('greeting_ar', 500)->nullable();
            $table->string('fallback_action', 24)->default('registration');
            $table->string('fallback_contact_email', 190)->nullable();
            $table->unsignedInteger('daily_question_limit')->default(500);
            $table->unsignedInteger('index_version')->default(0);
            $table->timestamp('indexed_at', 6)->nullable();
            $table->string('index_status', 24)->default('pending');
            $table->string('index_error_code', 64)->nullable();
            $table->unsignedInteger('chunk_count')->default(0);
            $table->timestamps(6);

            $table->unique(['tenant_id', 'event_id'], 'event_assistant_settings_event_unique');
            $table->foreign(['tenant_id', 'event_id'], 'event_assistant_settings_event_fk')
                ->references(['tenant_id', 'id'])->on('events')->cascadeOnDelete();
        });

        Schema::create('assistant_conversations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->char('public_id', 36);
            $table->string('locale', 5)->default('en');
            $table->char('visitor_hash', 64);
            $table->timestamp('started_at', 6);
            $table->timestamp('last_activity_at', 6);
            $table->unsignedInteger('turn_count')->default(0);
            $table->timestamp('purge_after', 6);
            $table->timestamps(6);

            $table->unique(['tenant_id', 'public_id'], 'assistant_conversations_public_unique');
            $table->unique(['tenant_id', 'event_id', 'id'], 'assistant_conversations_scope_unique');
            $table->index(['tenant_id', 'event_id', 'last_activity_at'], 'assistant_conversations_activity_idx');
            $table->index('purge_after', 'assistant_conversations_purge_idx');
            $table->foreign(['tenant_id', 'event_id'], 'assistant_conversations_event_fk')
                ->references(['tenant_id', 'id'])->on('events')->cascadeOnDelete();
        });

        Schema::create('assistant_turns', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('conversation_id');
            $table->text('question');
            $table->text('answer')->nullable();
            $table->string('outcome', 24);
            $table->json('citations')->nullable();
            $table->string('provider_key', 32)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->timestamps(6);

            $table->index(['tenant_id', 'event_id', 'outcome', 'created_at'], 'assistant_turns_outcome_idx');
            $table->foreign(['tenant_id', 'event_id', 'conversation_id'], 'assistant_turns_conversation_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('assistant_conversations')->cascadeOnDelete();
        });

        if ($this->supportsCheckConstraints()) {
            DB::statement("ALTER TABLE event_assistant_settings ADD CONSTRAINT event_assistant_fallback_chk CHECK (fallback_action IN ('registration','contact','none'))");
            DB::statement("ALTER TABLE event_assistant_settings ADD CONSTRAINT event_assistant_index_status_chk CHECK (index_status IN ('pending','ready','failed'))");
            DB::statement("ALTER TABLE assistant_turns ADD CONSTRAINT assistant_turns_outcome_chk CHECK (outcome IN ('answered','unanswered','refused','throttled','unavailable'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_turns');
        Schema::dropIfExists('assistant_conversations');
        Schema::dropIfExists('event_assistant_settings');
    }

    private function supportsCheckConstraints(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true);
    }
};
