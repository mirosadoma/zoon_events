<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE event_zones DROP CHECK event_zones_shape_type_chk');
        DB::statement("ALTER TABLE event_zones ADD CONSTRAINT event_zones_shape_type_chk CHECK (shape_type IS NULL OR shape_type IN ('polygon','rectangle','circle','triangle','hexagon','ellipse','pillar','person'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE event_zones DROP CHECK event_zones_shape_type_chk');
        DB::statement("ALTER TABLE event_zones ADD CONSTRAINT event_zones_shape_type_chk CHECK (shape_type IS NULL OR shape_type IN ('polygon','rectangle','circle','triangle','hexagon','ellipse'))");
    }
};
