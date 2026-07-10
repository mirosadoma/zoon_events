<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_events', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'event_id', 'id'], 'scan_events_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::table('scan_events', function (Blueprint $table): void {
            $table->dropUnique('scan_events_scope_unique');
        });
    }
};
