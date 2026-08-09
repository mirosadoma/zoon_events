<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_knowledge_chunks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedInteger('index_version');
            $table->string('source_type', 32);
            $table->string('source_id', 64);
            $table->string('locale', 5);
            $table->string('title', 255)->nullable();
            $table->text('content');
            $table->char('content_hash', 64);
            $table->json('embedding')->nullable();
            $table->string('embedding_model', 64)->nullable();
            $table->unsignedInteger('token_estimate')->default(0);
            $table->timestamps(6);

            $table->unique(
                ['tenant_id', 'event_id', 'index_version', 'content_hash'],
                'event_knowledge_chunks_dedupe_unique',
            );
            $table->index(['tenant_id', 'event_id', 'index_version'], 'event_knowledge_chunks_version_idx');
            $table->index(['tenant_id', 'event_id', 'locale'], 'event_knowledge_chunks_locale_idx');
            $table->foreign(['tenant_id', 'event_id'], 'event_knowledge_chunks_event_fk')
                ->references(['tenant_id', 'id'])->on('events')->cascadeOnDelete();
        });

        if ($this->supportsFullText()) {
            Schema::table('event_knowledge_chunks', function (Blueprint $table): void {
                $table->fullText(['content', 'title'], 'event_knowledge_chunks_fulltext');
            });
        }

        if ($this->supportsCheckConstraints()) {
            DB::statement("ALTER TABLE event_knowledge_chunks ADD CONSTRAINT event_knowledge_chunks_source_chk CHECK (source_type IN ('site_block','event_core','agenda','venue','zone','registration'))");
            DB::statement("ALTER TABLE event_knowledge_chunks ADD CONSTRAINT event_knowledge_chunks_locale_chk CHECK (locale IN ('en','ar'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_knowledge_chunks');
    }

    private function supportsFullText(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function supportsCheckConstraints(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true);
    }
};
