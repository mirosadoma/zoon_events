<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_venue_maps', function (Blueprint $table): void {
            $table->decimal('overlay_opacity', 3, 2)->default(0.85)->after('height');
            $table->boolean('remove_background')->default(false)->after('overlay_opacity');
            $table->boolean('show_base_map')->default(true)->after('remove_background');
        });
    }

    public function down(): void
    {
        Schema::table('event_venue_maps', function (Blueprint $table): void {
            $table->dropColumn(['overlay_opacity', 'remove_background', 'show_base_map']);
        });
    }
};
