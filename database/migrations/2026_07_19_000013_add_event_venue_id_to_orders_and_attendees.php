<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('event_venue_id')->nullable()->after('event_category_id');
            $table->index(['event_id', 'event_venue_id'], 'orders_event_venue_index');
        });

        Schema::table('attendees', function (Blueprint $table): void {
            $table->unsignedBigInteger('event_venue_id')->nullable()->after('user_id');
            $table->index(['event_id', 'event_venue_id'], 'attendees_event_venue_index');
        });
    }

    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $table): void {
            $table->dropIndex('attendees_event_venue_index');
            $table->dropColumn('event_venue_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_event_venue_index');
            $table->dropColumn('event_venue_id');
        });
    }
};
