<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_check_in_settings', function (Blueprint $table): void {
            $table->boolean('walk_up_registration_enabled')->default(false)->after('reprint_revokes_old_qr');
            $table->boolean('walk_up_payment_method_enabled')->default(false)->after('walk_up_registration_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('event_check_in_settings', function (Blueprint $table): void {
            $table->dropColumn(['walk_up_registration_enabled', 'walk_up_payment_method_enabled']);
        });
    }
};
