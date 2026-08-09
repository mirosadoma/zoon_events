<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_assistant_faqs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->string('question_en', 500);
            $table->string('question_ar', 500);
            $table->text('answer_en');
            $table->text('answer_ar');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);

            $table->foreign(['tenant_id', 'event_id'], 'event_assistant_faqs_event_fk')
                ->references(['tenant_id', 'id'])->on('events')->cascadeOnDelete();
            $table->index(
                ['tenant_id', 'event_id', 'is_active', 'sort_order'],
                'event_assistant_faqs_scope_idx',
            );
        });

        if ($this->supportsCheckConstraints()) {
            DB::statement('ALTER TABLE event_knowledge_chunks DROP CHECK event_knowledge_chunks_source_chk');
            DB::statement("ALTER TABLE event_knowledge_chunks ADD CONSTRAINT event_knowledge_chunks_source_chk CHECK (source_type IN ('site_block','event_core','agenda','venue','zone','registration','organizer_faq'))");
        }
    }

    public function down(): void
    {
        if ($this->supportsCheckConstraints()) {
            DB::statement('ALTER TABLE event_knowledge_chunks DROP CHECK event_knowledge_chunks_source_chk');
            DB::statement("ALTER TABLE event_knowledge_chunks ADD CONSTRAINT event_knowledge_chunks_source_chk CHECK (source_type IN ('site_block','event_core','agenda','venue','zone','registration'))");
        }

        Schema::dropIfExists('event_assistant_faqs');
    }

    private function supportsCheckConstraints(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
