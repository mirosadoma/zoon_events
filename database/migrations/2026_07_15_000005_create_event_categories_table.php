<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('category_template_id')->nullable();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('slug');
            $table->string('color', 7)->nullable();
            $table->integer('capacity')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('category_template_id')->references('id')->on('category_templates')->nullOnDelete();
            $table->unique(['event_id', 'slug']);
        });

        Schema::create('event_category_privileges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_category_id');
            $table->string('key');
            $table->string('label');
            $table->string('label_ar')->nullable();
            $table->string('effect')->default('allow'); // allow, deny
            $table->string('target_type')->nullable(); // gate, zone, parking, etc.
            $table->string('target_id')->nullable();
            $table->timestamps();

            $table->foreign('event_category_id')->references('id')->on('event_categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_category_privileges');
        Schema::dropIfExists('event_categories');
    }
};
