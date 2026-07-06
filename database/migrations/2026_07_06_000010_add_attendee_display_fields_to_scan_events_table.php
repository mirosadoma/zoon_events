<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_events', function (Blueprint $table): void {
            $table->text('attendee_display_name_ciphertext')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('scan_events', function (Blueprint $table): void {
            $table->dropColumn('attendee_display_name_ciphertext');
        });
    }
};
