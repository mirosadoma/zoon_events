<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_venue_maps', function (Blueprint $table) {
            $table->decimal('overlay_rotation', 8, 3)->default(0)->after('overlay_west');
        });
    }

    public function down(): void
    {
        Schema::table('event_venue_maps', function (Blueprint $table) {
            $table->dropColumn('overlay_rotation');
        });
    }
};
