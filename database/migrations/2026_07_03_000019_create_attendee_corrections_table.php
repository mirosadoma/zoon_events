<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendee_corrections', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('event_id', 26);
            $table->char('attendee_id', 26);
            $table->char('corrected_by_user_id', 26);
            $table->json('changed_fields');
            $table->string('reason', 500);
            $table->timestamp('created_at', 6);

            $table->foreign(['tenant_id', 'event_id', 'attendee_id'], 'attendee_corrections_attendee_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('attendees')->restrictOnDelete();
            $table->foreign('corrected_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['tenant_id', 'event_id', 'attendee_id', 'created_at'], 'attendee_corrections_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendee_corrections');
    }
};
