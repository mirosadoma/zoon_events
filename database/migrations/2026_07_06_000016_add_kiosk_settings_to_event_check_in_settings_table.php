<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_check_in_settings', function (Blueprint $table): void {
            $table->integer('kiosk_offline_threshold_seconds')->default(120)->after('single_entry_scope');
            $table->boolean('lookup_confirmation_required')->default(false)->after('kiosk_offline_threshold_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('event_check_in_settings', function (Blueprint $table): void {
            $table->dropColumn(['kiosk_offline_threshold_seconds', 'lookup_confirmation_required']);
        });
    }
};
