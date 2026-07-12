<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_agenda_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->string('title_en', 160);
            $table->string('title_ar', 160);
            $table->timestamp('start_at', 6);
            $table->timestamp('end_at', 6)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('created_at', 6)->useCurrent();
            $table->timestamp('updated_at', 6)->useCurrent()->useCurrentOnUpdate();

            $table->foreign(['tenant_id', 'event_id'])
                ->references(['tenant_id', 'id'])
                ->on('events')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'event_id', 'sort_order', 'start_at'], 'event_agenda_items_event_sort_index');
        });

        DB::statement('ALTER TABLE event_agenda_items ADD CONSTRAINT event_agenda_items_schedule_chk CHECK (end_at IS NULL OR end_at > start_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('event_agenda_items');
    }
};
