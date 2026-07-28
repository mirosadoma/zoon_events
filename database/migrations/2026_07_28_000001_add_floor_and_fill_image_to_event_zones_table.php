<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_zones', function (Blueprint $table): void {
            $table->string('floor_type', 16)->nullable()->after('type');
            $table->unsignedSmallInteger('floor_number')->nullable()->after('floor_type');
            $table->string('fill_image_path', 512)->nullable()->after('fill_color');
        });
    }

    public function down(): void
    {
        Schema::table('event_zones', function (Blueprint $table): void {
            $table->dropColumn(['floor_type', 'floor_number', 'fill_image_path']);
        });
    }
};
