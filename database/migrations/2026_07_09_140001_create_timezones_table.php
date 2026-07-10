<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timezones', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier', 64)->unique();
            $table->string('name_en', 160);
            $table->string('name_ar', 160);
            $table->string('region_en', 80)->nullable();
            $table->string('region_ar', 80)->nullable();
            $table->string('utc_offset', 8);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timezones');
    }
};
