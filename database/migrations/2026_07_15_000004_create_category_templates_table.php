<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('slug');
            $table->string('color', 7)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('category_template_privileges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_template_id');
            $table->string('key');
            $table->string('label');
            $table->string('label_ar')->nullable();
            $table->string('effect')->default('allow'); // allow, deny
            $table->string('target_type')->nullable(); // gate, zone, parking, etc.
            $table->string('target_id')->nullable();
            $table->timestamps();

            $table->foreign('category_template_id')->references('id')->on('category_templates')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_template_privileges');
        Schema::dropIfExists('category_templates');
    }
};
