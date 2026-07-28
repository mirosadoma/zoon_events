<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity_verifications', function (Blueprint $table): void {
            $table->text('verified_name_ciphertext')->nullable()->after('verified_name');
            $table->text('verified_nationality_ciphertext')->nullable()->after('verified_nationality');
            $table->string('encryption_key_id', 64)->nullable()->after('verified_nationality_ciphertext');
        });
    }

    public function down(): void
    {
        Schema::table('identity_verifications', function (Blueprint $table): void {
            $table->dropColumn([
                'verified_name_ciphertext',
                'verified_nationality_ciphertext',
                'encryption_key_id',
            ]);
        });
    }
};
