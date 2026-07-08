<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('attendee_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('credential_id')->nullable();
            $table->string('channel', 16);
            $table->string('template_key', 100);
            $table->string('template_version', 40);
            $table->char('locale', 2);
            $table->text('destination_ciphertext');
            $table->char('destination_index', 64);
            $table->string('encryption_key_id', 64);
            $table->char('content_digest', 64);
            $table->string('adapter_key', 64);
            $table->string('provider_message_id', 160)->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('next_attempt_at', 6)->nullable();
            $table->string('last_reason_code', 64)->nullable();
            $table->timestamps(6);

            $table->unique(['tenant_id', 'event_id', 'id'], 'notifications_scope_unique');
            $table->unique(['tenant_id', 'order_id', 'channel', 'template_key', 'template_version'], 'notifications_intent_unique');
            $table->foreign(['tenant_id', 'event_id', 'attendee_id'], 'notifications_attendee_fk')->references(['tenant_id', 'event_id', 'id'])->on('attendees')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'order_id'], 'notifications_order_fk')->references(['tenant_id', 'event_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'credential_id'], 'notifications_credential_fk')->references(['tenant_id', 'event_id', 'id'])->on('credentials')->restrictOnDelete();
            $table->index(['status', 'next_attempt_at', 'id'], 'notifications_delivery_index');
        });

        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_channel_chk CHECK (channel IN ('email','sms'))");
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_status_chk CHECK (status IN ('pending','processing','sent','delivered','temporary_failure','permanent_failure'))");
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_locale_chk CHECK (locale IN ('en','ar'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
