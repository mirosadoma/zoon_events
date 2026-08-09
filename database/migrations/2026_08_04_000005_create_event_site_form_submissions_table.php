<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_site_form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('event_site_id');
            $table->string('page_id', 64)->default('home');
            $table->string('page_title', 190)->nullable();
            $table->string('block_id', 64);
            $table->string('form_name', 190)->nullable();
            $table->json('payload');
            $table->char('visitor_hash', 64)->nullable();
            $table->string('locale', 5)->default('en');
            $table->timestamp('created_at', 6)->useCurrent();
            $table->timestamp('updated_at', 6)->useCurrent()->useCurrentOnUpdate();

            $table->index(['tenant_id', 'event_id', 'created_at'], 'event_site_forms_scope_created_idx');
            $table->index(['tenant_id', 'event_id', 'block_id'], 'event_site_forms_block_idx');
            $table->foreign(['tenant_id', 'event_id', 'event_site_id'], 'event_site_forms_site_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('event_sites')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_site_form_submissions');
    }
};
