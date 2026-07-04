<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->char('submission_id', 26)->nullable()->after('inventory_hold_id');
            $table->longText('fulfillment_payload_ciphertext')->nullable()->after('submission_id');
            $table->string('fulfillment_encryption_key_id', 64)->nullable()->after('fulfillment_payload_ciphertext');
            $table->timestamp('credential_expires_at', 6)->nullable()->after('fulfillment_encryption_key_id');

            $table->foreign(['tenant_id', 'event_id', 'submission_id'], 'orders_submission_fk')
                ->references(['tenant_id', 'event_id', 'id'])->on('registration_submissions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign('orders_submission_fk');
            $table->dropColumn([
                'submission_id',
                'fulfillment_payload_ciphertext',
                'fulfillment_encryption_key_id',
                'credential_expires_at',
            ]);
        });
    }
};
