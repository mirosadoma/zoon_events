<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_passes', function (Blueprint $table): void {
            $table->string('apple_authentication_token', 128)->nullable()->after('pass_url');
            $table->timestamp('pass_content_updated_at', 6)->nullable()->after('apple_authentication_token');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_passes', function (Blueprint $table): void {
            $table->dropColumn(['apple_authentication_token', 'pass_content_updated_at']);
        });
    }
};
