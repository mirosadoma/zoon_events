<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_paths', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('venue_id');
            $table->string('name_en', 160)->nullable();
            $table->string('name_ar', 160)->nullable();
            $table->json('polyline_coordinates');
            $table->unsignedBigInteger('from_zone_id')->nullable();
            $table->unsignedBigInteger('to_zone_id')->nullable();
            $table->string('stroke_color', 32)->nullable();
            $table->unsignedTinyInteger('stroke_width')->nullable();
            $table->unsignedTinyInteger('opacity')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('created_at', 6)->useCurrent();
            $table->timestamp('updated_at', 6)->useCurrent()->useCurrentOnUpdate();

            $table->foreign(['tenant_id', 'event_id'], 'event_paths_event_fk')
                ->references(['tenant_id', 'id'])
                ->on('events')
                ->cascadeOnDelete();

            $table->foreign('venue_id', 'event_paths_venue_fk')
                ->references('id')
                ->on('event_venues')
                ->cascadeOnDelete();

            $table->foreign('from_zone_id', 'event_paths_from_zone_fk')
                ->references('id')
                ->on('event_zones')
                ->nullOnDelete();

            $table->foreign('to_zone_id', 'event_paths_to_zone_fk')
                ->references('id')
                ->on('event_zones')
                ->nullOnDelete();

            $table->index(['tenant_id', 'event_id'], 'event_paths_tenant_event_index');
            $table->index(['venue_id'], 'event_paths_venue_index');
            $table->index(['event_id', 'venue_id'], 'event_paths_event_venue_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_paths');
    }
};
