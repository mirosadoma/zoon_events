<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_consents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('attendee_id')->nullable();
            $table->string('notice_version', 64);
            $table->json('disclosures');
            $table->string('residency_mode', 24)->default('on_premise');
            $table->timestamp('consented_at', 6);
            $table->timestamp('withdrawn_at', 6)->nullable();

            $table->foreign(['tenant_id', 'event_id', 'attendee_id'], 'identity_consents_attendee_fk')
                ->references(['tenant_id', 'event_id', 'id'])
                ->on('attendees')
                ->restrictOnDelete();
            $table->index(['tenant_id', 'event_id', 'attendee_id', 'consented_at'], 'identity_consents_lookup_index');
        });

        DB::statement(
            "ALTER TABLE identity_consents
            ADD CONSTRAINT identity_consents_residency_chk
            CHECK (residency_mode IN ('on_premise','saas'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_consents');
    }
};
