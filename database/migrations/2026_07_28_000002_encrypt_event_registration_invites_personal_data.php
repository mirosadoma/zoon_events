<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registration_invites', function (Blueprint $table): void {
            $table->text('email_ciphertext')->nullable()->after('email');
            $table->string('email_index', 64)->nullable()->after('email_ciphertext');
            $table->text('name_ciphertext')->nullable()->after('name');
            $table->string('encryption_key_id', 64)->nullable()->after('name_ciphertext');
            $table->index(['event_id', 'email_index'], 'eri_event_email_index_idx');
        });

        Schema::table('event_registration_invites', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('event_registration_invites', function (Blueprint $table): void {
            $table->dropIndex('eri_event_email_index_idx');
            $table->dropColumn([
                'email_ciphertext',
                'email_index',
                'name_ciphertext',
                'encryption_key_id',
            ]);
        });
    }
};
