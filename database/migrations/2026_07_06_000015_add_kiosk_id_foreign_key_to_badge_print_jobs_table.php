<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badge_print_jobs', function (Blueprint $table): void {
            $table->foreign('kiosk_id')->references('id')->on('kiosks')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('badge_print_jobs', function (Blueprint $table): void {
            $table->dropForeign(['kiosk_id']);
        });
    }
};
