<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_check_in_settings', function (Blueprint $table): void {
            $table->boolean('reprint_revokes_old_qr')->default(false)->after('lookup_confirmation_required');
        });
    }

    public function down(): void
    {
        Schema::table('event_check_in_settings', function (Blueprint $table): void {
            $table->dropColumn('reprint_revokes_old_qr');
        });
    }
};
