<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timezones', function (Blueprint $table): void {
            $table->string('country_en', 120)->nullable()->after('region_ar');
            $table->string('country_ar', 120)->nullable()->after('country_en');
        });
    }

    public function down(): void
    {
        Schema::table('timezones', function (Blueprint $table): void {
            $table->dropColumn(['country_en', 'country_ar']);
        });
    }
};
