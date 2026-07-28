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
            $table->string('coordinate_space', 16)->default('relative')->after('shape_type');
        });

        Schema::table('event_paths', function (Blueprint $table): void {
            $table->string('coordinate_space', 16)->default('relative')->after('polyline_coordinates');
        });

        // Existing rows stay relative until converted.
        DB::table('event_zones')->whereNull('coordinate_space')->update(['coordinate_space' => 'relative']);
        DB::table('event_paths')->whereNull('coordinate_space')->update(['coordinate_space' => 'relative']);
    }

    public function down(): void
    {
        Schema::table('event_zones', function (Blueprint $table): void {
            $table->dropColumn('coordinate_space');
        });

        Schema::table('event_paths', function (Blueprint $table): void {
            $table->dropColumn('coordinate_space');
        });
    }
};
