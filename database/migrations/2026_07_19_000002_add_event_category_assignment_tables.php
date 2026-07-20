<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_categories', function (Blueprint $table): void {
            $table->boolean('is_paid')->default(false)->after('capacity');
        });

        Schema::create('event_category_venues', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_category_id');
            $table->unsignedBigInteger('event_venue_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('event_category_id')->references('id')->on('event_categories')->cascadeOnDelete();
            $table->foreign('event_venue_id')->references('id')->on('event_venues')->cascadeOnDelete();
            $table->unique(['event_category_id', 'event_venue_id']);
        });

        Schema::create('event_category_venue_days', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_category_venue_id');
            $table->date('date');
            $table->unsignedInteger('capacity');
            $table->timestamps();

            $table->foreign('event_category_venue_id')->references('id')->on('event_category_venues')->cascadeOnDelete();
            $table->unique(['event_category_venue_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_category_venue_days');
        Schema::dropIfExists('event_category_venues');

        Schema::table('event_categories', function (Blueprint $table): void {
            $table->dropColumn('is_paid');
        });
    }
};
