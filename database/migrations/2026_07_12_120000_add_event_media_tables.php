<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('main_image_path', 500)->nullable()->after('capacity');
        });

        Schema::create('event_images', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->string('path', 500);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('created_at', 6)->useCurrent();
            $table->timestamp('updated_at', 6)->useCurrent()->useCurrentOnUpdate();

            $table->foreign(['tenant_id', 'event_id'])
                ->references(['tenant_id', 'id'])
                ->on('events')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'event_id', 'sort_order'], 'event_images_event_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_images');

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('main_image_path');
        });
    }
};
