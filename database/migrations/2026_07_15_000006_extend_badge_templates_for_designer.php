<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badge_templates', function (Blueprint $table) {
            $table->string('background_color', 7)->nullable()->after('printer_type');
            $table->string('background_image_path')->nullable()->after('background_color');
            $table->string('orientation', 10)->default('portrait')->after('background_image_path');
            $table->integer('canvas_width')->nullable()->after('orientation');
            $table->integer('canvas_height')->nullable()->after('canvas_width');
        });
    }

    public function down(): void
    {
        Schema::table('badge_templates', function (Blueprint $table) {
            $table->dropColumn(['background_color', 'background_image_path', 'orientation', 'canvas_width', 'canvas_height']);
        });
    }
};
