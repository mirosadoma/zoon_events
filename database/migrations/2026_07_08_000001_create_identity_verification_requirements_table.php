<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verification_requirements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('ticket_type_id')->nullable();
            $table->string('level', 32)->default('not_required');
            $table->boolean('face_fallback_enabled')->default(false);
            $table->timestamps(6);

            $table->unique(
                ['tenant_id', 'event_id', 'ticket_type_id'],
                'identity_requirements_scope_unique',
            );
            $table->foreign(['tenant_id', 'event_id'], 'identity_requirements_event_fk')
                ->references(['tenant_id', 'id'])
                ->on('events')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'ticket_type_id'], 'identity_requirements_ticket_fk')
                ->references(['tenant_id', 'event_id', 'id'])
                ->on('ticket_types')
                ->restrictOnDelete();
        });

        DB::statement(
            "ALTER TABLE identity_verification_requirements
            ADD CONSTRAINT identity_requirements_level_chk
            CHECK (level IN ('not_required','optional','required_before_credential','required_before_gate','required_vip','required_vvip'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_verification_requirements');
    }
};
