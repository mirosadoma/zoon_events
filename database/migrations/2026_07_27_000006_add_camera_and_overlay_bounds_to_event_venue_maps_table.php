<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_venue_maps', function (Blueprint $table): void {
            $table->decimal('map_center_lat', 10, 7)->nullable()->after('show_base_map');
            $table->decimal('map_center_lng', 10, 7)->nullable()->after('map_center_lat');
            $table->decimal('map_zoom', 5, 2)->nullable()->after('map_center_lng');
            $table->decimal('map_heading', 6, 2)->nullable()->after('map_zoom');
            $table->string('map_type', 32)->nullable()->after('map_heading');
            $table->decimal('overlay_north', 10, 7)->nullable()->after('map_type');
            $table->decimal('overlay_south', 10, 7)->nullable()->after('overlay_north');
            $table->decimal('overlay_east', 10, 7)->nullable()->after('overlay_south');
            $table->decimal('overlay_west', 10, 7)->nullable()->after('overlay_east');
        });
    }

    public function down(): void
    {
        Schema::table('event_venue_maps', function (Blueprint $table): void {
            $table->dropColumn([
                'map_center_lat',
                'map_center_lng',
                'map_zoom',
                'map_heading',
                'map_type',
                'overlay_north',
                'overlay_south',
                'overlay_east',
                'overlay_west',
            ]);
        });
    }
};
