<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_agenda_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('event_venue_id')->nullable()->after('event_id');
            $table->date('agenda_date')->nullable()->after('event_venue_id');
            $table->text('description_en')->nullable()->after('title_ar');
            $table->text('description_ar')->nullable()->after('description_en');

            $table->foreign('event_venue_id')
                ->references('id')
                ->on('event_venues')
                ->onDelete('cascade');

            $table->index(['event_id', 'event_venue_id', 'agenda_date']);
        });
    }

    public function down(): void
    {
        Schema::table('event_agenda_items', function (Blueprint $table): void {
            $table->dropForeign(['event_venue_id']);
            $table->dropIndex(['event_id', 'event_venue_id', 'agenda_date']);
            $table->dropColumn(['event_venue_id', 'agenda_date', 'description_en', 'description_ar']);
        });
    }
};
