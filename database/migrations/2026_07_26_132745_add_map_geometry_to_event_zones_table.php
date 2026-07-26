<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_zones', function (Blueprint $table): void {
            $table->string('shape_type', 32)->nullable()->after('capacity');
            $table->json('polygon_coordinates')->nullable()->after('shape_type');
            $table->decimal('shape_radius', 8, 6)->nullable()->after('polygon_coordinates');
            $table->string('label', 160)->nullable()->after('shape_radius');
            $table->string('google_maps_url', 1024)->nullable()->after('label');
            $table->decimal('lat', 10, 7)->nullable()->after('google_maps_url');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
            $table->string('fill_color', 32)->nullable()->after('lng');
            $table->string('stroke_color', 32)->nullable()->after('fill_color');
            $table->unsignedTinyInteger('opacity')->nullable()->after('stroke_color');
            $table->unsignedTinyInteger('stroke_width')->nullable()->after('opacity');
        });

        DB::statement('ALTER TABLE event_zones DROP CHECK event_zones_type_chk');
        DB::statement("ALTER TABLE event_zones ADD CONSTRAINT event_zones_type_chk CHECK (type IN ('hall','stage','room','vip','parking','outdoor','other'))");
        DB::statement("ALTER TABLE event_zones ADD CONSTRAINT event_zones_shape_type_chk CHECK (shape_type IS NULL OR shape_type IN ('polygon','rectangle','circle'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE event_zones DROP CHECK event_zones_shape_type_chk');
        DB::statement('ALTER TABLE event_zones DROP CHECK event_zones_type_chk');
        DB::statement("ALTER TABLE event_zones ADD CONSTRAINT event_zones_type_chk CHECK (type IN ('hall','stage','room','vip'))");

        Schema::table('event_zones', function (Blueprint $table): void {
            $table->dropColumn([
                'shape_type',
                'polygon_coordinates',
                'shape_radius',
                'label',
                'google_maps_url',
                'lat',
                'lng',
                'fill_color',
                'stroke_color',
                'opacity',
                'stroke_width',
            ]);
        });
    }
};
