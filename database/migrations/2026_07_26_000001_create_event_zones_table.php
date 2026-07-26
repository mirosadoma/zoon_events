<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_zones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('venue_id');
            $table->string('zone_name_en', 160);
            $table->string('zone_name_ar', 160);
            $table->string('type', 32);
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->timestamp('updated_at', 6)->useCurrent()->useCurrentOnUpdate();

            $table->foreign(['tenant_id', 'event_id'], 'event_zones_event_fk')
                ->references(['tenant_id', 'id'])
                ->on('events')
                ->cascadeOnDelete();

            $table->foreign('venue_id', 'event_zones_venue_fk')
                ->references('id')
                ->on('event_venues')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'event_id'], 'event_zones_tenant_event_index');
            $table->index(['venue_id'], 'event_zones_venue_index');
            $table->index(['event_id', 'venue_id'], 'event_zones_event_venue_index');
        });

        DB::statement("ALTER TABLE event_zones ADD CONSTRAINT event_zones_type_chk CHECK (type IN ('hall','stage','room','vip'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('event_zones');
    }
};
