<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_templates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->string('name', 120);
            $table->json('layout');
            $table->string('paper_size', 40);
            $table->string('printer_type', 40);
            // At most one active template per event is enforced in ActivateBadgeTemplateAction, not a DB constraint.
            $table->string('status', 20)->default('draft');
            $table->timestamps(6);

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('events')->restrictOnDelete();

            $table->unique(['tenant_id', 'event_id', 'id']);

            $table->index(['tenant_id', 'event_id', 'status']);
        });

        DB::statement("ALTER TABLE badge_templates ADD CONSTRAINT badge_templates_status_chk CHECK (status IN ('draft','active','inactive'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_templates');
    }
};
