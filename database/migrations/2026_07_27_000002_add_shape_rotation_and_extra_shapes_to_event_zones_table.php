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
            $table->decimal('shape_rotation', 8, 3)->default(0)->after('shape_radius');
            $table->decimal('shape_radius_y', 8, 6)->nullable()->after('shape_rotation');
        });

        DB::statement('ALTER TABLE event_zones DROP CHECK event_zones_shape_type_chk');
        DB::statement("ALTER TABLE event_zones ADD CONSTRAINT event_zones_shape_type_chk CHECK (shape_type IS NULL OR shape_type IN ('polygon','rectangle','circle','triangle','hexagon','ellipse'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE event_zones DROP CHECK event_zones_shape_type_chk');
        DB::statement("ALTER TABLE event_zones ADD CONSTRAINT event_zones_shape_type_chk CHECK (shape_type IS NULL OR shape_type IN ('polygon','rectangle','circle'))");

        Schema::table('event_zones', function (Blueprint $table): void {
            $table->dropColumn(['shape_rotation', 'shape_radius_y']);
        });
    }
};
