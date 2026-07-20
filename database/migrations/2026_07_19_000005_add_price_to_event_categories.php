<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_categories', function (Blueprint $table): void {
            $table->unsignedInteger('price_minor')->default(0)->after('is_paid');
            $table->char('currency', 3)->default('SAR')->after('price_minor');
        });
    }

    public function down(): void
    {
        Schema::table('event_categories', function (Blueprint $table): void {
            $table->dropColumn(['price_minor', 'currency']);
        });
    }
};
