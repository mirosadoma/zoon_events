<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_venue_maps', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('venue_id');
            $table->string('image_path', 512);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->timestamp('updated_at', 6)->useCurrent()->useCurrentOnUpdate();

            $table->foreign(['tenant_id', 'event_id'], 'event_venue_maps_event_fk')
                ->references(['tenant_id', 'id'])
                ->on('events')
                ->cascadeOnDelete();

            $table->foreign('venue_id', 'event_venue_maps_venue_fk')
                ->references('id')
                ->on('event_venues')
                ->cascadeOnDelete();

            $table->unique(['venue_id'], 'event_venue_maps_venue_unique');
            $table->index(['tenant_id', 'event_id'], 'event_venue_maps_tenant_event_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_venue_maps');
    }
};
