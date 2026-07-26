<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_agenda_items', function (Blueprint $table): void {
            $table->string('speaker', 160)->nullable()->after('description_ar');
            $table->unsignedBigInteger('zone_id')->nullable()->after('event_venue_id');

            $table->foreign('zone_id', 'event_agenda_items_zone_fk')
                ->references('id')
                ->on('event_zones')
                ->nullOnDelete();

            $table->index(['event_id', 'zone_id'], 'event_agenda_items_event_zone_index');
            $table->index(
                ['event_id', 'event_venue_id', 'zone_id', 'agenda_date'],
                'event_agenda_items_filter_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('event_agenda_items', function (Blueprint $table): void {
            $table->dropForeign('event_agenda_items_zone_fk');
            $table->dropIndex('event_agenda_items_event_zone_index');
            $table->dropIndex('event_agenda_items_filter_index');
            $table->dropColumn(['speaker', 'zone_id']);
        });
    }
};
