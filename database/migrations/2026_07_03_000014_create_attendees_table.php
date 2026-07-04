<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendees', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('tenant_id', 26);
            $table->char('event_id', 26);
            $table->char('order_id', 26);
            $table->char('order_item_id', 26)->unique();
            $table->char('ticket_type_id', 26);
            $table->char('submission_id', 26);
            $table->text('first_name_ciphertext');
            $table->text('last_name_ciphertext');
            $table->text('email_ciphertext');
            $table->text('phone_ciphertext')->nullable();
            $table->char('email_index', 64);
            $table->char('phone_index', 64)->nullable();
            $table->string('encryption_key_id', 64);
            $table->string('registration_status', 24)->default('registered');
            $table->char('preferred_locale', 2);
            $table->timestamp('registered_at', 6);
            $table->timestamp('cancelled_at', 6)->nullable();
            $table->timestamp('anonymized_at', 6)->nullable();
            $table->timestamps(6);

            $table->unique(['tenant_id', 'event_id', 'id'], 'attendees_scope_unique');
            $table->foreign(['tenant_id', 'event_id', 'order_id'], 'attendees_order_fk')->references(['tenant_id', 'event_id', 'id'])->on('orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'order_item_id'], 'attendees_item_fk')->references(['tenant_id', 'event_id', 'id'])->on('order_items')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'ticket_type_id'], 'attendees_ticket_fk')->references(['tenant_id', 'event_id', 'id'])->on('ticket_types')->restrictOnDelete();
            $table->foreign(['tenant_id', 'event_id', 'submission_id'], 'attendees_submission_fk')->references(['tenant_id', 'event_id', 'id'])->on('registration_submissions')->restrictOnDelete();
            $table->index(['tenant_id', 'event_id', 'email_index', 'registered_at', 'id'], 'attendees_email_index');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'event_id', 'attendee_id'], 'order_items_attendee_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('attendees')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE attendees ADD CONSTRAINT attendees_status_chk CHECK (registration_status IN ('registered','cancelled','anonymized'))");
        DB::statement("ALTER TABLE attendees ADD CONSTRAINT attendees_locale_chk CHECK (preferred_locale IN ('en','ar'))");
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropForeign('order_items_attendee_fk'));
        Schema::dropIfExists('attendees');
    }
};
