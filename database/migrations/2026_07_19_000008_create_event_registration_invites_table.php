<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registration_invites', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('event_id');
            $table->string('email');
            $table->string('code', 10);
            $table->boolean('is_active')->default(true);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->unique(['event_id', 'code'], 'eri_event_code_unique');
            $table->index(['event_id', 'email'], 'eri_event_email_index');
            $table->index(['tenant_id', 'event_id', 'is_active'], 'eri_tenant_event_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_invites');
    }
};
