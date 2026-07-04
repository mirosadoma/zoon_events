<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendees', function (Blueprint $table): void {
            $table->timestamp('legal_hold_at', 6)->nullable()->after('anonymized_at');
            $table->string('legal_hold_reference', 120)->nullable()->after('legal_hold_at');
            $table->index(['tenant_id', 'registration_status', 'legal_hold_at', 'registered_at'], 'attendees_retention_index');
        });
    }

    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $table): void {
            $table->dropIndex('attendees_retention_index');
            $table->dropColumn(['legal_hold_at', 'legal_hold_reference']);
        });
    }
};
