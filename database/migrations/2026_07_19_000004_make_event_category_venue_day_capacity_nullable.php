<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_category_venue_days', function (Blueprint $table): void {
            $table->unsignedInteger('capacity')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('event_category_venue_days', function (Blueprint $table): void {
            $table->unsignedInteger('capacity')->nullable(false)->change();
        });
    }
};
