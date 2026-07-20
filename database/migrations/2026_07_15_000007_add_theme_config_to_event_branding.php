<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_branding', function (Blueprint $table) {
            $table->json('theme_config')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('event_branding', function (Blueprint $table) {
            $table->dropColumn('theme_config');
        });
    }
};
