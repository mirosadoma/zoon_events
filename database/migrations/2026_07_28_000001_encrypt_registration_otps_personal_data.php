<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_otps', function (Blueprint $table): void {
            $table->text('email_ciphertext')->nullable()->after('email');
            $table->string('email_index', 64)->nullable()->after('email_ciphertext');
            $table->longText('payload_ciphertext')->nullable()->after('payload');
            $table->string('encryption_key_id', 64)->nullable()->after('payload_ciphertext');
            $table->index(['event_id', 'email_index'], 'registration_otps_event_email_index_idx');
        });

        Schema::table('registration_otps', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->json('payload')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('registration_otps', function (Blueprint $table): void {
            $table->dropIndex('registration_otps_event_email_index_idx');
            $table->dropColumn([
                'email_ciphertext',
                'email_index',
                'payload_ciphertext',
                'encryption_key_id',
            ]);
        });
    }
};
