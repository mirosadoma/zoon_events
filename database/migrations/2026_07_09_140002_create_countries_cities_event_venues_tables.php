<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name_en', 160);
            $table->string('name_ar', 160);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->string('name_en', 160);
            $table->string('name_ar', 160);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['country_id', 'name_en']);
        });

        Schema::create('event_venues', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('name_en', 160);
            $table->string('name_ar', 160);
            $table->text('location_address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('start_at', 6)->nullable();
            $table->timestamp('end_at', 6)->nullable();
            $table->timestamp('registration_opens_at', 6)->nullable();
            $table->timestamp('registration_closes_at', 6)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign(['tenant_id', 'event_id'], 'event_venues_event_fk')
                ->references(['tenant_id', 'id'])
                ->on('events')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_venues');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('countries');
    }
};
