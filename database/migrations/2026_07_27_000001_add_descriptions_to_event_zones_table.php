<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_zones', function (Blueprint $table): void {
            $table->text('description_en')->nullable()->after('zone_name_ar');
            $table->text('description_ar')->nullable()->after('description_en');
        });
    }

    public function down(): void
    {
        Schema::table('event_zones', function (Blueprint $table): void {
            $table->dropColumn(['description_en', 'description_ar']);
        });
    }
};
