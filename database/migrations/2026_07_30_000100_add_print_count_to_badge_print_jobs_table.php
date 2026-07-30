<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badge_print_jobs', function (Blueprint $table): void {
            $table->unsignedInteger('print_count')->default(1)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('badge_print_jobs', function (Blueprint $table): void {
            $table->dropColumn('print_count');
        });
    }
};
