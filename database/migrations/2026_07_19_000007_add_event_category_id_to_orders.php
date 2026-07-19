<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('event_category_id')->nullable()->after('submission_id');
            $table->index(['tenant_id', 'event_id', 'event_category_id'], 'orders_event_category_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_event_category_index');
            $table->dropColumn('event_category_id');
        });
    }
};
